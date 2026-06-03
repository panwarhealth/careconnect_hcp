"""Shared helpers for the careconnect_hcp reporting scripts.

Import as:

    import sys
    from pathlib import Path
    sys.path.insert(0, str(Path(__file__).resolve().parent.parent))  # reports/
    from lib import ga4, db, mailchimp, periods, io

See reports/README.md for the auth setup and how to run a quarterly review.
"""
