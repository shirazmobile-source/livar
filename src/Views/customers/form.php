<?php $errors = validation_errors(); ?>
<?php
$documents = $customer['documents'] ?? [];
$selectedType = (string) old('customer_type', $customer['customer_type']);
$isBusiness = $selectedType === 'business';
?>
<section class="page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <small>Maintain customer information, attachments, and contact profile details. Invoice currency is selected later inside each invoice; accounting remains AED-based.</small>
    </div>
    <div class="page-head-actions">
        <?php if (!empty($customer['id'])): ?>
            <a href="<?= e(base_url('/customers/show?id=' . $customer['id'])) ?>" class="btn secondary">Statement</a>
        <?php endif; ?>
        <a href="<?= e(base_url('/customers')) ?>" class="btn secondary">Back</a>
    </div>
</section>

<section class="card">
    <div class="card-b">
        <?php require __DIR__ . '/../partials/form_errors.php'; ?>

        <form method="post" action="<?= e(base_url($action)) ?>" enctype="multipart/form-data" class="stack-gap-sm customer-form">
            <?= App\Core\Csrf::field() ?>

            <div class="field-grid field-grid-2 customer-primary-grid">
                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= e((string) old('code', $customer['code'])) ?>" required>
                </div>
                <div class="field">
                    <label>Accounting Base</label>
                    <input type="text" value="AED — UAE Dirham" readonly class="readonly-input">
                    <small>Customer profiles are AED-based. You can choose any invoice currency later when issuing sale invoices.</small>
                </div>
            </div>

            <div class="field customer-type-field">
                <label>Customer Type</label>
                <div class="customer-type-stack" data-customer-type-switch>
                    <label class="type-option type-option-vertical <?= e($isBusiness ? 'is-active' : '') ?>">
                        <input type="radio" name="customer_type" value="business" <?= checked('business', $selectedType) ?> data-customer-type>
                        <span class="type-option-icon" aria-hidden="true">🏢</span>
                        <span class="type-option-copy">
                            <strong>Business</strong>
                            <small>Company account, trade license, TRN, and corporate contacts.</small>
                        </span>
                    </label>
                    <label class="type-option type-option-vertical <?= e(!$isBusiness ? 'is-active' : '') ?>">
                        <input type="radio" name="customer_type" value="individual" <?= checked('individual', $selectedType) ?> data-customer-type>
                        <span class="type-option-icon" aria-hidden="true">👤</span>
                        <span class="type-option-copy">
                            <strong>Individual</strong>
                            <small>Private person profile for retail, walk-in, or personal customers.</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="field-grid field-grid-2 customer-name-grid">
                <div class="field <?= e($isBusiness ? '' : 'is-hidden') ?>" data-type-panel="business">
                    <label>Company Name</label>
                    <input
                        type="text"
                        name="company_name"
                        value="<?= e((string) old('company_name', $customer['company_name'] ?? '')) ?>"
                        placeholder="Enter company name"
                        data-type-input="business"
                        <?= $isBusiness ? '' : 'disabled' ?>
                    >
                </div>
                <div class="field <?= e(!$isBusiness ? '' : 'is-hidden') ?>" data-type-panel="individual">
                    <label>Person Name</label>
                    <input
                        type="text"
                        name="person_name"
                        value="<?= e((string) old('person_name', $customer['person_name'] ?? '')) ?>"
                        placeholder="Enter person name"
                        data-type-input="individual"
                        <?= !$isBusiness ? '' : 'disabled' ?>
                    >
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= selected('active', old('status', $customer['status'])) ?>>Active</option>
                        <option value="inactive" <?= selected('inactive', old('status', $customer['status'])) ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="field-grid field-grid-2 customer-contact-grid">
                <div class="field">
                    <label>Mobile (E.164)</label>
                    <input
                        type="text"
                        name="mobile"
                        value="<?= e((string) old('mobile', $customer['mobile'])) ?>"
                        placeholder="+971 50 000 0000"
                        data-customer-mobile
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <small>Start with the country code, for example <strong>+971</strong>. The country is detected automatically.</small>
                    <div class="country-chip" data-country-chip>
                        <?= e(((string) old('country_code', $customer['country_code'] ?? '')) !== '' ? ((string) old('country_code', $customer['country_code'] ?? '') . ' · ' . (string) old('country_name', $customer['country_name'] ?? '')) : 'Country will be detected from the + code') ?>
                    </div>
                    <input type="hidden" name="country_code" value="<?= e((string) old('country_code', $customer['country_code'] ?? '')) ?>" data-country-code>
                    <input type="hidden" name="country_name" value="<?= e((string) old('country_name', $customer['country_name'] ?? '')) ?>" data-country-name>
                </div>
                <div class="field">
                    <label>Detected Country</label>
                    <input type="text" value="<?= e((string) old('country_name', $customer['country_name'] ?? '')) ?>" data-country-display readonly class="readonly-input" placeholder="Country will be detected automatically">
                </div>
                <div class="field">
                    <label>TRN Number</label>
                    <input type="text" name="trn_number" value="<?= e((string) old('trn_number', $customer['trn_number'] ?? '')) ?>" placeholder="TRN / Tax registration number">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e((string) old('email', $customer['email'] ?? '')) ?>" placeholder="email@example.com">
                </div>
            </div>

            <div class="field">
                <label>Address</label>
                <textarea name="address" placeholder="Office address, billing address, or notes"><?= e((string) old('address', $customer['address'] ?? '')) ?></textarea>
            </div>

            <div class="field-grid field-grid-2 customer-media-grid">
                <div class="media-card">
                    <div class="media-card-head">
                        <h3>Customer Profile</h3>
                        <small>Upload a profile image for quick identification in lists and future CRM views.</small>
                    </div>
                    <div class="media-preview-block">
                        <?php if (!empty($customer['profile_image_path'])): ?>
                            <img src="<?= e(public_upload_url($customer['profile_image_path'])) ?>" alt="Customer profile" class="customer-profile-preview-image">
                            <label class="checkbox-line"><input type="checkbox" name="remove_profile_image" value="1"> Remove current profile image</label>
                        <?php else: ?>
                            <div class="product-preview-empty">No profile image uploaded.</div>
                        <?php endif; ?>
                    </div>
                    <div class="field field-spacer-top">
                        <label>Profile Image</label>
                        <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                </div>

                <div class="media-card">
                    <div class="media-card-head">
                        <h3>Customer Documents</h3>
                        <small>Attach trade license, ID copy, tax certificate, or other customer files.</small>
                    </div>
                    <div class="field">
                        <label>Attach Files</label>
                        <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        <small>Supported files: PDF, JPG, JPEG, PNG. Each file can be up to 8 MB.</small>
                    </div>

                    <?php if ($documents !== []): ?>
                        <div class="document-list">
                            <?php foreach ($documents as $document): ?>
                                <label class="document-item">
                                    <input type="checkbox" name="remove_document_ids[]" value="<?= e((string) $document['id']) ?>">
                                    <div class="document-copy">
                                        <strong><a href="<?= e(public_upload_url($document['file_path'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($document['original_name']) ?></a></strong>
                                        <small><?= e(($document['file_ext'] ?? 'FILE') . ' · ' . format_bytes((int) ($document['file_size'] ?? 0))) ?></small>
                                    </div>
                                    <span class="muted-xs">Remove</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="product-preview-empty">No attachments added yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="<?= e(base_url('/customers')) ?>" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</section>
