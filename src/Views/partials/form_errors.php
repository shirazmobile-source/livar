<?php if (!empty($errors)): ?>
    <div class="alert" style="margin-bottom: 12px;">
        <strong>Please review the form.</strong>
        <ul class="error-list">
            <?php foreach ($errors as $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
