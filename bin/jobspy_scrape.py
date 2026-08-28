#!/usr/bin/env python3
"""LinkedIn (and JobSpy sites) scrape helper for KaamFit — stdin JSON → stdout JSON records."""
from __future__ import annotations

import json
import sys

from jobspy import scrape_jobs


def main() -> None:
    try:
        raw = sys.stdin.read()
        if not raw.strip():
            print(json.dumps({"error": "No JSON input provided"}), file=sys.stderr)
            sys.exit(1)

        args = json.loads(raw)
        site_name = args.get("site_name", ["linkedin"])
        if isinstance(site_name, str):
            site_name = [site_name]

        kwargs = {
            "site_name": site_name,
            "search_term": args.get("search_term", "") or "",
            "location": args.get("location", "") or "",
            "results_wanted": int(args.get("results_wanted", 20)),
            "country_indeed": args.get("country_indeed", "Germany"),
            "description_format": args.get("description_format", "markdown"),
        }
        hours_old = args.get("hours_old")
        if hours_old is not None and str(hours_old).strip() != "":
            kwargs["hours_old"] = int(hours_old)
        if args.get("is_remote") is True:
            kwargs["is_remote"] = True
        # LinkedIn omits description/date unless this is on (slower but usable JD).
        if any(str(s).lower() == "linkedin" for s in site_name):
            kwargs["linkedin_fetch_description"] = bool(
                args.get("linkedin_fetch_description", True)
            )

        jobs = scrape_jobs(**kwargs)
        # Normalize to list[dict] for Freeworld\PhpJobspy\Scrapers\PythonJobspyScraper
        records = json.loads(jobs.to_json(orient="records", date_format="iso"))
        print(json.dumps(records))
    except Exception as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
