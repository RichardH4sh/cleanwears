<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

auth_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf_token'] ?? null);

    $accountName   = trim((string) ($_POST['account_name'] ?? ''));
    $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
    $bankName      = trim((string) ($_POST['bank_name'] ?? ''));

    if ($accountName === '' || $accountNumber === '' || $bankName === '') {
        flash_set('error', 'All three fields are required.');
    } else {
        // Only one bank account is shown to customers at a time: deactivate any
        // existing active row, then insert the new one as active. This keeps a
        // full history in the table rather than silently overwriting old data.
        $db = db();
        $db->beginTransaction();
        $db->exec("UPDATE bank_accounts SET is_active = 0 WHERE is_active = 1");
        $stmt = $db->prepare(
            'INSERT INTO bank_accounts (account_name, account_number, bank_name, is_active) VALUES (:n, :a, :b, 1)'
        );
        $stmt->execute(['n' => $accountName, 'a' => $accountNumber, 'b' => $bankName]);
        $db->commit();
        flash_set('success', 'Bank account details updated.');
    }
    redirect('bank-account.php');
}

$stmt = db()->query('SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
$current = $stmt->fetch() ?: null;

$pageTitle = 'Bank Account';
$active = 'bank';
require __DIR__ . '/_header.php';
?>
<section class="panel panel-narrow">
  <h2>Payment details shown to customers at checkout</h2>
  <p class="field-hint">Customers see these details on the checkout page and are asked to make a manual bank transfer before confirming their purchase.</p>
  <form method="post" action="bank-account.php" class="stack-form">
    <?= csrf_field() ?>
    <label for="account_name">Account name</label>
    <input type="text" id="account_name" name="account_name" required maxlength="150" value="<?= e($current['account_name'] ?? '') ?>">

    <label for="account_number">Account number</label>
    <input type="text" id="account_number" name="account_number" required maxlength="50" value="<?= e($current['account_number'] ?? '') ?>">

    <label for="bank_name">Bank name</label>
    <input type="text" id="bank_name" name="bank_name" required maxlength="150" value="<?= e($current['bank_name'] ?? '') ?>">

    <div class="form-actions">
      <button type="submit">Save details</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
