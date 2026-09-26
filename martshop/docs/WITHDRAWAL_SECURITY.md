# Withdrawal security — 2026-09-08

Merchant withdrawal management, administrator withdrawal review and withdrawal-proof downloads now use the strict private-financial response middleware. These responses receive `no-store, private`, `no-referrer`, frame denial, MIME-sniffing protection and the restricted financial CSP. The merchant sees only masked destination identifiers; authorized withdrawal reviewers can see the full destination only inside the private administration page.

## Reauthentication and separation of duties

Every administrator-side withdrawal mutation now requires the current Mart.ps account password:

- changing withdrawal availability or limits;
- approving or rejecting a merchant payout method;
- approving or rejecting a withdrawal request;
- recording an approved withdrawal as paid.

The password is checked with Laravel's authenticated `web` guard and is never flashed back into the session. Transfer references, transfer times and free-form financial review notes are also excluded from flashed validation input.

An administrator account associated with a merchant cannot review that merchant's payout method, approve or reject its withdrawal, or record its withdrawal as paid. Own records are omitted from that reviewer's queue, and the restriction is repeated inside the locked transactional services so a crafted request cannot bypass the interface. A blocked attempt leaves withdrawal, payout-method, proof, ledger and audit state unchanged.

## Proof integrity

Withdrawal proofs are accepted only on private `local` storage at the generated `withdrawal-proofs/{withdrawal_id}/{uuid}.{allowed-extension}` path. Before recording a view audit or returning bytes, the download controller verifies:

- the stored disk and exact generated path shape;
- real-path confinement within that withdrawal's directory;
- existence as a regular file;
- stored versus actual file size;
- a valid stored SHA-256 and a matching actual hash.

Invalid, missing, traversing or modified records fail closed with 404 and do not produce a successful-view audit. A valid file is returned as an attachment with `application/octet-stream` and a generated filename, never the user-supplied original filename.

## Verification

Four additional feature tests cover private responses and pre-disclosure authorization, failed administrator reauthentication without secret flashing or writes, enforced self-review separation, and proof path/size/hash/disk integrity. The focused withdrawal/dashboard suite passed: **19 tests, 305 assertions**. The complete suite passed: **198 tests, 1963 assertions**. All Blade templates compiled, PHP syntax checks passed, and the 143 registered routes have no duplicate names or method/URI signatures.

Tests use SQLite `:memory:` and fake private storage. Withdrawal intake remains disabled in the working settings, and this implementation did not approve, reject or pay any live withdrawal or modify working financial data. HTTPS, isolated reviewer accounts, operational monitoring and an independent production-engine concurrency test remain deployment requirements.
