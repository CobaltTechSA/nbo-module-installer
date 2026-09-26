function oneLine(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

async function gitOutput(exec, args, options = {}) {
    const result = await exec.getExecOutput('git', args, {
        ignoreReturnCode: options.ignoreReturnCode ?? false,
        silent: options.silent ?? true,
    });

    return {
        exitCode: result.exitCode,
        stdout: result.stdout.trim(),
    };
}

async function tagExists(exec, tag) {
    const result = await gitOutput(exec, ['rev-parse', '--verify', `${tag}^{commit}`], {
        ignoreReturnCode: true,
    });

    return result.exitCode === 0;
}

async function isAncestor(exec, ancestor, descendant) {
    const result = await gitOutput(exec, ['merge-base', '--is-ancestor', ancestor, descendant], {
        ignoreReturnCode: true,
    });

    return result.exitCode === 0;
}

async function previousPublishedRelease({github, context, core, exec, tag}) {
    const {owner, repo} = context.repo;
    const releases = await github.paginate(github.rest.repos.listReleases, {
        owner,
        repo,
        per_page: 100,
    });
    const candidates = releases
        .filter((release) => !release.draft && release.published_at && release.tag_name !== tag)
        .sort((left, right) => Date.parse(right.published_at) - Date.parse(left.published_at));

    for (const release of candidates) {
        if (!await tagExists(exec, release.tag_name)) {
            core.warning(`Published release tag [${release.tag_name}] is unavailable locally.`);
            continue;
        }

        if (await isAncestor(exec, release.tag_name, tag)) {
            return release;
        }
    }

    return null;
}

async function readCommits(exec, range) {
    const result = await gitOutput(exec, ['log', '--reverse', '--format=%H%x1f%s%x1f%an', range]);

    if (!result.stdout) {
        return [];
    }

    return result.stdout.split('\n').filter(Boolean).map((line) => {
        const [sha, subject, author] = line.split('\x1f');

        return {
            sha,
            subject: oneLine(subject),
            author: oneLine(author),
        };
    });
}

async function associatedMergedPullRequests(github, owner, repo, sha) {
    const pulls = await github.paginate(github.rest.repos.listPullRequestsAssociatedWithCommit, {
        owner,
        repo,
        commit_sha: sha,
        per_page: 100,
    });

    return pulls.filter((pull) => pull.merged_at);
}

async function pullRequestFromMergeMessage(github, owner, repo, subject) {
    const match = subject.match(/^Merge pull request #(\d+)\b/i);

    if (!match) {
        return null;
    }

    const response = await github.rest.pulls.get({
        owner,
        repo,
        pull_number: Number(match[1]),
    });

    return response.data.merged_at ? response.data : null;
}

function compareUrl(owner, repo, previousTag, tag) {
    if (!previousTag) {
        return `https://github.com/${owner}/${repo}/commits/${encodeURIComponent(tag)}`;
    }

    const base = encodeURIComponent(previousTag);
    const head = encodeURIComponent(tag);

    return `https://github.com/${owner}/${repo}/compare/${base}...${head}`;
}

function renderReleaseNotes({owner, repo, tag, previousTag, pullRequests, directCommits}) {
    const lines = [];

    if (previousTag) {
        lines.push(`Changes since \`${previousTag}\`.`, '');
    } else {
        lines.push('Initial published release.', '');
    }

    lines.push('## Pull requests', '');

    if (pullRequests.length === 0) {
        lines.push('_No merged pull requests in this release._');
    } else {
        for (const pull of pullRequests) {
            const author = pull.user?.login ? ` (@${pull.user.login})` : '';
            lines.push(`- [#${pull.number}](${pull.html_url}) ${oneLine(pull.title)}${author}`);
        }
    }

    lines.push('', '## Direct commits', '');

    if (directCommits.length === 0) {
        lines.push('_No direct commits in this release._');
    } else {
        for (const commit of directCommits) {
            const shortSha = commit.sha.slice(0, 7);
            const url = `https://github.com/${owner}/${repo}/commit/${commit.sha}`;
            const author = commit.author ? ` — ${commit.author}` : '';

            lines.push(`- [\`${shortSha}\`](${url}) ${commit.subject}${author}`);
        }
    }

    lines.push(
        '',
        '## Full changelog',
        '',
        `[Compare changes](${compareUrl(owner, repo, previousTag, tag)})`,
        '',
    );

    const notes = `${lines.join('\n')}\n`;
    const maxLength = 125000;

    if (notes.length <= maxLength) {
        return notes;
    }

    const changelogIndex = lines.indexOf('## Full changelog');
    const changelog = `\n\n${lines.slice(changelogIndex).join('\n')}\n`;
    const changes = lines.slice(0, changelogIndex).join('\n');
    const suffix = '... and other changes';
    const availableChangesLength = maxLength - changelog.length - suffix.length - 2;
    const lastLineBreak = changes.lastIndexOf('\n', availableChangesLength);
    const shortenedChanges = changes.slice(0, Math.max(0, lastLineBreak)).trimEnd();

    return `${shortenedChanges}\n\n${suffix}${changelog}`;
}

async function createOrUpdateRelease({github, context, tag, notes}) {
    const {owner, repo} = context.repo;
    let existingRelease = null;

    try {
        const response = await github.rest.repos.getReleaseByTag({
            owner,
            repo,
            tag,
        });

        existingRelease = response.data;
    } catch (error) {
        if (error.status !== 404) {
            throw error;
        }
    }

    let releaseName = `Version ${tag}`;

    if (existingRelease) {
        await github.rest.repos.updateRelease({
            owner,
            repo,
            release_id: existingRelease.id,
            tag_name: tag,
            name: releaseName,
            body: notes,
            draft: false,
            prerelease: false,
        });

        return;
    }

    await github.rest.repos.createRelease({
        owner,
        repo,
        tag_name: tag,
        name: releaseName,
        body: notes,
        draft: false,
        prerelease: false,
    });
}

module.exports = async function publishRelease({github, context, core, exec, tag}) {
    const {owner, repo} = context.repo;

    if (!tag || !tag.startsWith('v')) {
        throw new Error(`Invalid release tag [${tag || ''}]. Expected a tag starting with v.`);
    }

    if (!await tagExists(exec, tag)) {
        throw new Error(`Release tag [${tag}] is not available in the checkout.`);
    }

    const previousRelease = await previousPublishedRelease({
        github,
        context,
        core,
        exec,
        tag,
    });
    const previousTag = previousRelease?.tag_name ?? null;
    const range = previousTag ? `${previousTag}..${tag}` : tag;
    const commits = await readCommits(exec, range);
    const pullRequests = new Map();
    const directCommits = [];

    for (const commit of commits) {
        let pulls = await associatedMergedPullRequests(github, owner, repo, commit.sha);

        if (pulls.length === 0) {
            const mergePull = await pullRequestFromMergeMessage(github, owner, repo, commit.subject);
            pulls = mergePull ? [mergePull] : [];
        }

        if (pulls.length === 0) {
            directCommits.push(commit);
            continue;
        }

        for (const pull of pulls) {
            pullRequests.set(pull.number, pull);
        }
    }

    const sortedPullRequests = [...pullRequests.values()].sort((left, right) => {
        return Date.parse(left.merged_at) - Date.parse(right.merged_at);
    });
    const notes = renderReleaseNotes({
        owner,
        repo,
        tag,
        previousTag,
        pullRequests: sortedPullRequests,
        directCommits,
    });

    await createOrUpdateRelease({
        github,
        context,
        tag,
        notes,
    });

    core.info(`Published release [${tag}].`);
    core.info(`Previous release: ${previousTag ?? 'none'}.`);
    core.info(`Included ${sortedPullRequests.length} pull request(s).`);
    core.info(`Included ${directCommits.length} direct commit(s).`);

    await core.summary.addHeading(`Release ${tag}`).addRaw(notes).write();
};
