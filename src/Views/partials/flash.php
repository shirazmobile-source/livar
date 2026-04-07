<?php $success = flash('success'); $error = flash('error'); ?>
<?php if ($success): ?>
    <div class="notice stack-gap-sm">
        <strong>Success:</strong> <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert stack-gap-sm">
        <strong>Error:</strong> <?= e($error) ?>
    </div>
<?php endif; ?>
