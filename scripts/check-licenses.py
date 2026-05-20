import json
import sys

FORBIDDEN = ['proprietary', 'commercial', 'BSL-1.0', 'SSPL-1.0', 'Commons-Clause']

try:
    data = json.load(sys.stdin)
except json.JSONDecodeError as e:
    print(f"WARN: Не удалось разобрать JSON: {e}")
    sys.exit(0)

violations = []
for pkg, info in data.get('dependencies', {}).items():
    licenses = info.get('license', [])
    if isinstance(licenses, str):
        licenses = [licenses]
    for lic in licenses:
        for f in FORBIDDEN:
            if f.lower() in lic.lower():
                violations.append(f"{pkg}: {lic}")

if violations:
    print("FAIL: Несовместимые лицензии найдены:")
    for v in violations:
        print(f"  - {v}")
    sys.exit(1)
else:
    print("OK: Все лицензии совместимы с AGPLv3")
