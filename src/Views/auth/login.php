<?php $errors = validation_errors(); ?>
<section class="auth-card card">
    <div class="card-h">
        <div>
            <h1><?= e((string) config('app.name', 'LCA')) ?></h1>
            <small>Sign in to manage customers, stock, purchases, sales, and reports.</small>
        </div>
        <span class="badge orange">English UI</span>
    </div>
    <div class="card-b">
        <?php require __DIR__ . '/../partials/form_errors.php'; ?>

        <form method="post" action="<?= e(base_url('/login')) ?>" class="stack-form">
            <?= App\Core\Csrf::field() ?>

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="<?= e((string) old('email')) ?>" placeholder="admin@example.com" required>
            </div>

            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="row auth-actions auth-actions-single">
                <button type="submit" class="btn btn-auth-submit">Sign in</button>
            </div>
        </form>
    </div>
</section>
