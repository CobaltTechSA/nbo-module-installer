<?php

namespace Neopayment\NboInstaller\Console;

use Neopayment\NboInstaller\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class NewModuleCommand extends Command
{
    protected static $defaultName = 'module:new';

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new NBO module')
            ->addArgument('code', InputArgument::REQUIRED, 'Module code, example: customers')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Display name')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Target path')
            ->addOption('composer-vendor', null, InputOption::VALUE_OPTIONAL, 'Composer vendor', 'neopayment')
            ->addOption('npm-scope', null, InputOption::VALUE_OPTIONAL, 'NPM scope', '@neopayment')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'PHP root namespace', 'Neopayment')
            ->addOption('github-org', null, InputOption::VALUE_OPTIONAL, 'GitHub organization', 'neopayment')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();

        $code = Str::kebab((string) $input->getArgument('code'));
        $snake = Str::snake($code);
        $studly = Str::studly($code);
        $displayName = $input->getOption('name') ?: Str::title($code);

        $composerVendor = Str::kebab((string) $input->getOption('composer-vendor'));
        $npmScope = (string) $input->getOption('npm-scope');
        $phpRootNamespace = trim((string) $input->getOption('namespace'), '\\');
        $githubOrg = Str::kebab((string) $input->getOption('github-org'));

        $targetPath = $input->getOption('path')
            ?: getcwd().DIRECTORY_SEPARATOR.'nbo-module-'.$code;

        $targetPath = rtrim((string) $targetPath, DIRECTORY_SEPARATOR);

        if ($filesystem->exists($targetPath) && ! $input->getOption('force')) {
            $output->writeln("<error>Target directory already exists: {$targetPath}</error>");
            $output->writeln('Use --force to overwrite existing files.');

            return Command::FAILURE;
        }

        $composerPackage = "{$composerVendor}/nbo-module-{$code}";
        $npmPackage = rtrim($npmScope, '/')."/nbo-module-{$code}";
        $phpNamespace = "{$phpRootNamespace}\\NboModule{$studly}";
        $serviceProviderClass = "NboModule{$studly}ServiceProvider";
        $seederClass = "{$studly}ModuleSeeder";

        $replacements = [
            '{{MODULE_CODE}}' => $code,
            '{{MODULE_SNAKE}}' => $snake,
            '{{MODULE_STUDLY}}' => $studly,
            '{{MODULE_NAME}}' => $displayName,

            '{{COMPOSER_VENDOR}}' => $composerVendor,
            '{{COMPOSER_PACKAGE}}' => $composerPackage,

            '{{NPM_SCOPE}}' => $npmScope,
            '{{NPM_PACKAGE}}' => $npmPackage,

            '{{PHP_ROOT_NAMESPACE}}' => $phpRootNamespace,
            '{{PHP_NAMESPACE}}' => $phpNamespace,
            '{{PHP_NAMESPACE_JSON}}' => str_replace('\\', '\\\\', $phpNamespace),

            '{{SERVICE_PROVIDER_CLASS}}' => $serviceProviderClass,
            '{{SEEDER_CLASS}}' => $seederClass,

            '{{GITHUB_ORG}}' => $githubOrg,
            '{{GITHUB_REPOSITORY}}' => "nbo-module-{$code}",
        ];

        $stubPath = dirname(__DIR__, 2).'/stubs/module';

        $filesystem->mkdir($targetPath);

        $this->copyStubDirectory($stubPath, $targetPath, $replacements, $filesystem);

        $this->renameGeneratedFiles($targetPath, $code, $snake, $serviceProviderClass, $seederClass, $filesystem);

        $output->writeln('');
        $output->writeln('<info>NBO module created successfully.</info>');
        $output->writeln('');
        $output->writeln("Path:             <comment>{$targetPath}</comment>");
        $output->writeln("Composer package: <comment>{$composerPackage}</comment>");
        $output->writeln("NPM package:      <comment>{$npmPackage}</comment>");
        $output->writeln("PHP namespace:    <comment>{$phpNamespace}</comment>");
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln("  cd {$targetPath}");
        $output->writeln('  composer install');
        $output->writeln('  npm install');
        $output->writeln('  git init');
        $output->writeln('  git add .');
        $output->writeln('  git commit -m "Initial module scaffold"');

        return Command::SUCCESS;
    }

    private function copyStubDirectory(
        string $source,
        string $target,
        array $replacements,
        Filesystem $filesystem
    ): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            $targetFile = $target.'/'.$relativePath;

            if ($item->isDir()) {
                $filesystem->mkdir($targetFile);
                continue;
            }

            if (str_ends_with($targetFile, '.stub')) {
                $targetFile = substr($targetFile, 0, -5);
            }

            $contents = file_get_contents($item->getPathname());

            foreach ($replacements as $search => $replace) {
                $contents = str_replace($search, $replace, $contents);
            }

            $filesystem->mkdir(dirname($targetFile));
            file_put_contents($targetFile, $contents);
        }
    }

    private function renameGeneratedFiles(
        string $targetPath,
        string $code,
        string $snake,
        string $serviceProviderClass,
        string $seederClass,
        Filesystem $filesystem
    ): void {
        $renames = [
            "{$targetPath}/src/ModuleServiceProvider.php" => "{$targetPath}/src/{$serviceProviderClass}.php",
            "{$targetPath}/database/seeders/ModuleSeeder.php" => "{$targetPath}/database/seeders/{$seederClass}.php",
            "{$targetPath}/database/migrations/create_module_tables.php" => "{$targetPath}/database/migrations/create_{$snake}_module_tables.php",
        ];

        foreach ($renames as $from => $to) {
            if ($filesystem->exists($from)) {
                $filesystem->rename($from, $to, true);
            }
        }
    }
}