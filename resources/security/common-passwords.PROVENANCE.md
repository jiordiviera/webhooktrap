# Common-password list: provenance

- **Source:** Mark Burnett's "10 million passwords" dataset, as carried in
  SecLists:
  `Passwords/Common-Credentials/xato-net-10-million-passwords.txt`.
    - Repository: https://github.com/danielmiessler/SecLists, commit
      `51aa7644c96b3fb6db8de9ecf55aa91195209c80`.
    - sha256 of the source file:
      `c95989fecfae9c91aa9f5c730344d5cabd6335bb094c9c60aba16bf1742a72fe`.
- **Licence:** SecLists is MIT. The licence text is in
  `common-passwords.LICENSE-SecLists`. The PO confirms the licence before
  the first public release (A09 slice 1 review).
- **Filtering:**
    1. Each line is decoded as UTF-8; invalid lines are skipped.
    2. Each entry is normalised to NFC; non-printable entries are skipped.
    3. Only entries of 12 to 128 code points are kept.
    4. Entries are lowercased and de-duplicated, keeping the source's
       frequency order.
    5. The list stops at the first 100,000 entries.
    6. The project's earlier hand-picked seed entries are kept on top.
- **Date:** 2026-11-15 (A00, decision 56).
