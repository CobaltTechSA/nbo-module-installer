#!/usr/bin/env python3
import os
import re
import json
import subprocess
from pathlib import Path

PAN_CANDIDATE_RE = re.compile(r"\b(?:\d[ -]*?){13,19}\b")

EXCLUDED_PATH_PARTS = {
    "node_modules/",
    "vendor/",
    "dist/",
    "build/",
    ".git/",
    "coverage/",
}

EXCLUDED_EXTENSIONS = {
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".pdf",
    ".zip", ".gz", ".jar", ".war", ".exe", ".dll",
    ".woff", ".woff2", ".ttf", ".map",
}

def luhn_ok(number: str) -> bool:
    digits = [int(d) for d in number]
    checksum = 0
    parity = len(digits) % 2
    for i, d in enumerate(digits):
        if i % 2 == parity:
            d *= 2
            if d > 9:
                d -= 9
        checksum += d
    return checksum % 10 == 0

def is_excluded(path: str) -> bool:
    p = path.replace("\\", "/")
    if any(part in p for part in EXCLUDED_PATH_PARTS):
        return True
    return Path(p).suffix.lower() in EXCLUDED_EXTENSIONS

def changed_files(base_ref: str) -> list[str]:
    cmd = ["git", "diff", "--name-only", f"{base_ref}...HEAD"]
    result = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return [line.strip() for line in result.stdout.splitlines() if line.strip()]

def get_added_lines(base_ref: str, path: str) -> list[tuple[int, str]]:
    cmd = ["git", "diff", "--unified=0", f"{base_ref}...HEAD", "--", path]
    result = subprocess.run(cmd, capture_output=True, text=True, check=True)
    lines = result.stdout.splitlines()

    findings = []
    new_line_no = None

    for line in lines:
        if line.startswith("@@"):
            # Example: @@ -10,0 +11,3 @@
            m = re.search(r"\+(\d+)(?:,(\d+))?", line)
            if m:
                new_line_no = int(m.group(1))
            continue

        if line.startswith("+") and not line.startswith("+++"):
            if new_line_no is not None:
                findings.append((new_line_no, line[1:]))
                new_line_no += 1
        elif not line.startswith("-"):
            if new_line_no is not None:
                new_line_no += 1

    return findings

def main():
    base_ref = os.environ.get("BASE_REF", "origin/main")
    findings = []

    for path in changed_files(base_ref):
        if is_excluded(path):
            continue
        file_path = Path(path)
        if not file_path.exists() or not file_path.is_file():
            continue

        for line_no, line in get_added_lines(base_ref, path):
            for match in PAN_CANDIDATE_RE.finditer(line):
                raw = match.group(0)
                digits = re.sub(r"[^0-9]", "", raw)
                if 13 <= len(digits) <= 19 and luhn_ok(digits):
                    findings.append({
                        "file": path,
                        "line": line_no,
                        "message": "PAN detected in PR",
                        "masked": f"{digits[:6]}******{digits[-4:]}" if len(digits) >= 10 else "masked",
                    })

    if findings:
        print("Possible PANs were detected in the PR:\n")
        for f in findings:
            print(f"- {f['file']}:{f['line']} -> {f['message']} [{f['masked']}]")

        # Reporte genérico para SonarCloud
        report = {
            "rules": [
                {
                    "id": "custom-pan-detection",
                    "name": "PAN should not be present in source code",
                    "description": "Detects possible Primary Account Numbers in new code of PR.",
                    "engineId": "custom-pan-scanner",
                    "cleanCodeAttribute": "TRUSTWORTHY",
                    "type": "VULNERABILITY",
                    "severity": "CRITICAL",
                    "impacts": [
                        {
                            "softwareQuality": "SECURITY",
                            "severity": "BLOCKER"
                        }
                    ]
                }
            ],
            "issues": [
                {
                    "ruleId": "custom-pan-detection",
                    "primaryLocation": {
                        "message": f["message"],
                        "filePath": f["file"],
                        "textRange": {
                            "startLine": f["line"],
                            "endLine": f["line"],
                            "startColumn": 1,
                            "endColumn": 1
                        }
                    }
                }
                for f in findings
            ]
        }

        os.makedirs(".sonar", exist_ok=True)
        with open(".sonar/pan-report.json", "w", encoding="utf-8") as fh:
            json.dump(report, fh, indent=2)

        raise SystemExit(1)

    print("No potential PANs were detected on new PR lines.")
    os.makedirs(".sonar", exist_ok=True)
    with open(".sonar/pan-report.json", "w", encoding="utf-8") as fh:
        json.dump({"rules": [], "issues": []}, fh, indent=2)

if __name__ == "__main__":
    main()
