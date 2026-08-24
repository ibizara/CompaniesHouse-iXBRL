# Annual filing rollover

The annual rollover is designed to minimise repetitive edits while keeping a human confirmation step.

## Preconditions

- The current submission has a saved `ACCEPT` response in `out/`.
- The next accounting period has ended.
- The new accounts have actually been approved.
- The company details, eligibility, figures and taxonomy have been checked.

## What the rollover proposes

For values such as:

```text
AC0001   → AC0002
IXBRL001 → IXBRL002
```

it also:

- advances the current accounting period by one year;
- makes the old current period the new comparative period;
- derives the balance-sheet date and displayed years;
- generates the section 477 sentence from the new period end;
- uses one selected approval date for `date_signed`, `date_document` and the visible board approval date;
- copies old current-year facts to the new comparative facts;
- pre-fills new current-year facts for checking;
- creates a timestamped backup before replacing `storage/filing.php`.

## Steps

1. Open `admin.php`.
2. Click **Prepare next filing**.
3. Confirm the accepted status shown at the top.
4. Review the proposed submission number and customer reference.
5. Enter the actual approval date.
6. Review the current and comparative periods.
7. Check every financial and employee fact.
8. Check the officer and statutory wording.
9. Verify the current Companies House taxonomy/schema guidance.
10. Tick every confirmation and apply.
11. Build the iXBRL again.
12. Review and validate `out/output.xhtml`.
13. Submit once.
14. Poll the status and acknowledge the returned status.

## Double-increment protection

The page requires a matching accepted-status file for the current submission. After rollover, the submission number changes, so the old acceptance no longer matches and cannot be used to increment it again.

Do not delete the accepted status response until after the next filing has been prepared and backed up.
