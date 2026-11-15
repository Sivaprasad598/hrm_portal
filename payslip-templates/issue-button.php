<?php
// "Issue payslip" on a draft (screen only; payslips.php sets $issueAction for
// whoever may approve payslips). The same approval as Payroll -> Issued
// Payslips -> Approve; afterwards the slip is shown again, no longer a draft.
if (empty($issueAction)) {
    return;
}
?>
<button type="button" id="issue-payslip" style="background:#027a48;border-color:#027a48;color:#fff;"
        title="Approve this draft so it counts as issued (Payroll &rarr; Issued Payslips shows it as Approved)">✔ Issue payslip</button>
<script>
    (function () {
        var button = document.getElementById('issue-payslip');
        if (!button) { return; }

        button.addEventListener('click', function () {
            if (!window.confirm('Issue this payslip? It stops being a draft and can then be marked paid and e-mailed.')) { return; }

            button.disabled = true;
            button.textContent = 'Issuing…';

            fetch(<?= json_encode($issueAction['url']) ?>, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': <?= json_encode($issueAction['token']) ?>
                },
                body: JSON.stringify({ ids: [<?= (int) $issueAction['id'] ?>] })
            }).then(function (response) {
                return response.json().catch(function () { return {}; });
            }).then(function (data) {
                if (data && data.created > 0) {
                    window.location.href = <?= json_encode($issueAction['view']) ?>;
                    return;
                }

                window.alert((data && data.reasons && data.reasons.length ? data.reasons.join('\n') : (data && data.message) || 'The payslip could not be issued.'));
                button.disabled = false;
                button.textContent = '✔ Issue payslip';
            }).catch(function () {
                window.alert('The payslip could not be issued.');
                button.disabled = false;
                button.textContent = '✔ Issue payslip';
            });
        });
    })();
</script>
