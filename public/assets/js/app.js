(function () {
    const html = document.documentElement;
    const defaultTheme = html.dataset.defaultTheme || html.getAttribute('data-theme') || 'dark';
    const themeRevision = html.dataset.themeRevision || 'core-default';
    const savedRevision = localStorage.getItem('livar-theme-revision');
    const savedTheme = savedRevision === themeRevision ? localStorage.getItem('livar-theme') : null;

    const applyTheme = (theme) => {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        html.setAttribute('data-theme', nextTheme);

        document.querySelectorAll('[data-theme-icon]').forEach((icon) => {
            icon.textContent = nextTheme === 'dark' ? '☾' : '☀';
        });

        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const switchLabel = nextTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            button.setAttribute('aria-label', switchLabel);
            button.removeAttribute('title');
        });
    };

    applyTheme(savedTheme || defaultTheme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);
            localStorage.setItem('livar-theme', nextTheme);
            localStorage.setItem('livar-theme-revision', themeRevision);
        });
    });

    const createLineRow = (table) => {
        const firstRow = table.querySelector('[data-line-row]');
        if (!firstRow) {
            return null;
        }

        const clone = firstRow.cloneNode(true);
        clone.querySelectorAll('select').forEach((element) => {
            if (element.hasAttribute('data-pricing-unit')) {
                element.value = 'unit';
            } else {
                element.value = '';
            }
        });
        clone.querySelectorAll('input').forEach((element) => {
            if (element.hasAttribute('data-stock-display') || element.hasAttribute('data-line-total')) {
                element.value = '0.00';
            } else if (element.hasAttribute('data-qty')) {
                element.value = '1';
            } else if (element.hasAttribute('data-price')) {
                element.value = '0';
                delete element.dataset.userEdited;
            } else if (element.hasAttribute('data-price-aed') || element.hasAttribute('data-discount-aed')) {
                element.value = '';
            } else if (element.hasAttribute('data-units-per-box-hidden')) {
                element.value = '1';
            } else {
                element.value = '';
            }
        });

        return clone;
    };

    const invoiceScopeFor = (element) => element.closest('[data-invoice-form]') || document;

    const selectedWarehouseId = (scope) => Number(scope.querySelector('[data-warehouse-select]')?.value || 0);

    const stockForOption = (scope, option) => {
        if (!option) return 0;
        if ((option.dataset.itemType || 'inventory') !== 'inventory') {
            return null;
        }
        const warehouseId = selectedWarehouseId(scope);
        const stockMapRaw = option.dataset.stockMap || '';

        if (warehouseId > 0 && stockMapRaw) {
            try {
                const parsed = JSON.parse(stockMapRaw);
                if (parsed && Object.prototype.hasOwnProperty.call(parsed, String(warehouseId))) {
                    return Number(parsed[String(warehouseId)] || 0);
                }
            } catch (error) {
                // Ignore malformed stock map data and fall back to aggregate stock.
            }
        }

        return Number(option.dataset.stock || 0);
    };

    const invoiceMeta = (scope) => {
        const select = scope.querySelector('[data-invoice-currency-select]');
        const option = select?.options[select.selectedIndex];
        const rate = Math.max(Number(option?.dataset.rate || 1), 0.00000001);

        return {
            select,
            option,
            rate,
            code: option?.dataset.code || 'AED',
            symbol: option?.dataset.symbol || 'د.إ'
        };
    };

    const updateInvoiceLabels = (scope) => {
        const meta = invoiceMeta(scope);
        scope.querySelectorAll('[data-invoice-currency-code]').forEach((node) => {
            node.textContent = meta.code;
        });
        scope.querySelectorAll('[data-invoice-rate-view]').forEach((node) => {
            node.textContent = meta.rate.toFixed(4);
        });
    };

    const syncDiscountShadowFromInput = (scope) => {
        const meta = invoiceMeta(scope);
        const input = scope.querySelector('[data-discount]');
        const shadow = scope.querySelector('[data-discount-aed]');
        if (!input || !shadow) {
            return;
        }
        shadow.value = (Number(input.value || 0) * meta.rate).toFixed(2);
    };

    const syncDiscountInputFromShadow = (scope) => {
        const meta = invoiceMeta(scope);
        const input = scope.querySelector('[data-discount]');
        const shadow = scope.querySelector('[data-discount-aed]');
        if (!input || !shadow) {
            return;
        }

        const shadowValue = Number(shadow.value || 0);
        if (shadowValue > 0 || Number(input.value || 0) > 0) {
            input.value = (shadowValue / meta.rate).toFixed(2);
        }
    };

    const rowUnitsPerBox = (row, option = null) => {
        const hidden = row.querySelector('[data-units-per-box-hidden]');
        const optionFactor = Number(option?.dataset.unitsPerBox || hidden?.value || 1);
        const safeFactor = optionFactor > 0 ? optionFactor : 1;
        if (hidden) {
            hidden.value = safeFactor.toFixed(2);
        }
        return safeFactor;
    };

    const rowDisplayFactor = (row, option = null) => {
        const pricingUnit = row.querySelector('[data-pricing-unit]')?.value || 'unit';
        const factor = rowUnitsPerBox(row, option);
        return pricingUnit === 'box' && factor > 1 ? factor : 1;
    };

    const refreshPricingUnitOptions = (row, option = null) => {
        const pricingUnitSelect = row.querySelector('[data-pricing-unit]');
        if (!pricingUnitSelect) {
            return;
        }

        const itemType = option?.dataset.itemType || 'inventory';
        const factor = itemType === 'inventory' ? rowUnitsPerBox(row, option) : 1;
        const unitLabel = option?.dataset.unitLabel || 'Unit';
        const boxOption = pricingUnitSelect.querySelector('option[value="box"]');

        if (boxOption) {
            if (itemType === 'inventory' && factor > 1) {
                boxOption.disabled = false;
                boxOption.textContent = `Box (${factor.toFixed(2).replace(/\.00$/, '')} ${unitLabel})`;
            } else {
                boxOption.disabled = true;
                boxOption.textContent = 'Box';
                if (pricingUnitSelect.value === 'box') {
                    pricingUnitSelect.value = 'unit';
                }
            }
        }
    };

    const syncRowFromProduct = (row, table) => {
        const scope = invoiceScopeFor(table);
        const meta = invoiceMeta(scope);
        const select = row.querySelector('[data-product-select]');
        const priceInput = row.querySelector('[data-price]');
        const priceAedInput = row.querySelector('[data-price-aed]');
        const stockInput = row.querySelector('[data-stock-display]');
        const option = select?.options[select.selectedIndex];
        const basePriceAed = Number(option?.dataset.basePriceAed || 0);
        const stock = stockForOption(scope, option);
        const isInventoryItem = (option?.dataset.itemType || 'inventory') === 'inventory';

        refreshPricingUnitOptions(row, option);

        if (stockInput) {
            stockInput.value = stock === null ? 'N/A' : Number(stock || 0).toFixed(2);
        }

        if (!select || !priceInput || !priceAedInput) {
            return;
        }

        if (!select.value) {
            priceInput.value = '0';
            priceAedInput.value = '';
            delete priceInput.dataset.userEdited;
            return;
        }

        const factor = rowDisplayFactor(row, option);
        priceAedInput.value = basePriceAed.toFixed(2);
        priceInput.value = ((basePriceAed * factor) / meta.rate).toFixed(2);
        delete priceInput.dataset.userEdited;
    };

    const syncRowFromCurrency = (row, table) => {
        const scope = invoiceScopeFor(table);
        const meta = invoiceMeta(scope);
        const select = row.querySelector('[data-product-select]');
        const priceInput = row.querySelector('[data-price]');
        const priceAedInput = row.querySelector('[data-price-aed]');
        const stockInput = row.querySelector('[data-stock-display]');
        const option = select?.options[select.selectedIndex];
        const stock = stockForOption(scope, option);
        const isInventoryItem = (option?.dataset.itemType || 'inventory') === 'inventory';
        const fallbackBasePriceAed = Number(option?.dataset.basePriceAed || 0);

        refreshPricingUnitOptions(row, option);

        if (stockInput) {
            stockInput.value = stock === null ? 'N/A' : Number(stock || 0).toFixed(2);
        }

        if (!select || !priceInput || !priceAedInput || !select.value) {
            return;
        }

        let priceAed = Number(priceAedInput.value || 0);
        if (priceAed <= 0) {
            priceAed = fallbackBasePriceAed;
            priceAedInput.value = priceAed.toFixed(2);
        }

        const factor = rowDisplayFactor(row, option);
        priceInput.value = ((priceAed * factor) / meta.rate).toFixed(2);
    };

    const syncRowFromPrice = (row, table) => {
        const scope = invoiceScopeFor(table);
        const meta = invoiceMeta(scope);
        const priceInput = row.querySelector('[data-price]');
        const priceAedInput = row.querySelector('[data-price-aed]');
        const select = row.querySelector('[data-product-select]');
        const option = select?.options[select.selectedIndex];
        const factor = rowDisplayFactor(row, option);
        if (!priceInput || !priceAedInput) {
            return;
        }

        priceAedInput.value = ((Number(priceInput.value || 0) / factor) * meta.rate).toFixed(2);
    };

    const refreshRowReadonly = (row) => {
        const scope = invoiceScopeFor(row);
        const select = row.querySelector('[data-product-select]');
        const stockInput = row.querySelector('[data-stock-display]');
        const qtyInput = row.querySelector('[data-qty]');
        const priceInput = row.querySelector('[data-price]');
        const lineTotalInput = row.querySelector('[data-line-total]');
        const option = select?.options[select.selectedIndex];
        const stock = stockForOption(scope, option);
        const isInventoryItem = (option?.dataset.itemType || 'inventory') === 'inventory';
        const factor = rowDisplayFactor(row, option);

        refreshPricingUnitOptions(row, option);

        if (stockInput) {
            stockInput.value = stock === null ? 'N/A' : Number(stock || 0).toFixed(2);
        }

        if (!qtyInput || !priceInput || !lineTotalInput) {
            return { invoice: 0, aed: 0 };
        }

        const qty = Number(qtyInput.value || 0);
        const price = Number(priceInput.value || 0);
        const priceAed = Number(row.querySelector('[data-price-aed]')?.value || 0);
        const lineTotal = qty * price;
        const lineTotalAed = qty * factor * priceAed;

        lineTotalInput.value = lineTotal.toFixed(2);

        return {
            invoice: lineTotal,
            aed: lineTotalAed
        };
    };

    const refreshTable = (table) => {
        const scope = invoiceScopeFor(table);
        const subtotal = { invoice: 0, aed: 0 };
        table.querySelectorAll('[data-line-row]').forEach((row) => {
            const rowTotals = refreshRowReadonly(row);
            subtotal.invoice += rowTotals.invoice;
            subtotal.aed += rowTotals.aed;
        });

        const card = table.closest('.card-b');
        if (!card) {
            return;
        }

        const discountInput = card.querySelector('[data-discount]');
        const discountShadow = card.querySelector('[data-discount-aed]');
        const subtotalNode = card.querySelector('[data-subtotal]');
        const discountNode = card.querySelector('[data-discount-view]');
        const finalNode = card.querySelector('[data-final-total]');
        const subtotalAedNode = card.querySelector('[data-subtotal-aed]');
        const discountAedNode = card.querySelector('[data-discount-view-aed]');
        const finalAedNode = card.querySelector('[data-final-total-aed]');

        const discountInvoice = Number(discountInput?.value || 0);
        const discountAed = Number(discountShadow?.value || 0);
        const finalInvoice = Math.max(0, subtotal.invoice - discountInvoice);
        const finalAed = Math.max(0, subtotal.aed - discountAed);

        if (subtotalNode) subtotalNode.textContent = subtotal.invoice.toFixed(2);
        if (discountNode) discountNode.textContent = discountInvoice.toFixed(2);
        if (finalNode) finalNode.textContent = finalInvoice.toFixed(2);
        if (subtotalAedNode) subtotalAedNode.textContent = subtotal.aed.toFixed(2);
        if (discountAedNode) discountAedNode.textContent = discountAed.toFixed(2);
        if (finalAedNode) finalAedNode.textContent = finalAed.toFixed(2);
    };

    document.querySelectorAll('.line-items-table').forEach((table) => {
        const tbody = table.querySelector('[data-lines]');
        if (!tbody) return;

        const scope = invoiceScopeFor(table);
        updateInvoiceLabels(scope);
        syncDiscountShadowFromInput(scope);

        table.querySelectorAll('[data-line-row]').forEach((row) => {
            const select = row.querySelector('[data-product-select]');
            const priceInput = row.querySelector('[data-price]');
            const priceAedInput = row.querySelector('[data-price-aed]');
            const option = select?.options[select.selectedIndex];

            refreshPricingUnitOptions(row, option);

            if (select?.value) {
                if (priceAedInput && Number(priceAedInput.value || 0) <= 0 && priceInput) {
                    syncRowFromPrice(row, table);
                }
                if (priceInput && (priceInput.value === '' || Number(priceInput.value || 0) === 0) && priceAedInput && Number(priceAedInput.value || 0) > 0) {
                    syncRowFromCurrency(row, table);
                }
            }
        });

        table.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.matches('[data-product-select]')) {
                syncRowFromProduct(target.closest('[data-line-row]'), table);
                refreshTable(table);
                return;
            }

            if (target.matches('[data-pricing-unit]')) {
                syncRowFromProduct(target.closest('[data-line-row]'), table);
                refreshTable(table);
                return;
            }

            if (target.matches('[data-qty], [data-price]')) {
                if (target.matches('[data-price]')) {
                    target.dataset.userEdited = '1';
                    syncRowFromPrice(target.closest('[data-line-row]'), table);
                }
                refreshTable(table);
            }
        });

        table.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.matches('[data-price]')) {
                target.dataset.userEdited = '1';
                syncRowFromPrice(target.closest('[data-line-row]'), table);
                refreshTable(table);
                return;
            }

            if (target.matches('[data-qty]')) {
                refreshTable(table);
            }
        });

        table.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.matches('[data-remove-line]')) {
                const rows = table.querySelectorAll('[data-line-row]');
                if (rows.length > 1) {
                    target.closest('[data-line-row]')?.remove();
                    refreshTable(table);
                }
            }
        });

        const addButton = table.closest('section, .card')?.querySelector('[data-add-line]');
        if (addButton) {
            addButton.addEventListener('click', () => {
                const newRow = createLineRow(table);
                if (newRow) {
                    tbody.appendChild(newRow);
                    refreshTable(table);
                }
            });
        }

        const currencySelect = scope.querySelector('[data-invoice-currency-select]');
        if (currencySelect && !currencySelect.dataset.boundCurrencyChange) {
            currencySelect.dataset.boundCurrencyChange = '1';
            currencySelect.addEventListener('change', () => {
                updateInvoiceLabels(scope);
                syncDiscountInputFromShadow(scope);
                syncDiscountShadowFromInput(scope);
                scope.querySelectorAll('[data-line-row]').forEach((row) => syncRowFromCurrency(row, table));
                refreshTable(table);
            });
        }

        const warehouseSelect = scope.querySelector('[data-warehouse-select]');
        if (warehouseSelect && !warehouseSelect.dataset.boundWarehouseChange) {
            warehouseSelect.dataset.boundWarehouseChange = '1';
            warehouseSelect.addEventListener('change', () => {
                scope.querySelectorAll('[data-line-row]').forEach((row) => {
                    syncRowFromCurrency(row, table);
                    refreshRowReadonly(row);
                });
                refreshTable(table);
            });
        }

        const discountInput = scope.querySelector('[data-discount]');
        if (discountInput && !discountInput.dataset.boundDiscountInput) {
            discountInput.dataset.boundDiscountInput = '1';
            discountInput.addEventListener('input', () => {
                syncDiscountShadowFromInput(scope);
                refreshTable(table);
            });
        }

        refreshTable(table);
    });

    const productCodeInput = document.querySelector('[data-product-code]');
    const qrPreview = document.querySelector('[data-qr-preview]');
    const qrValue = document.querySelector('[data-qr-value]');
    const productPurchaseInput = document.querySelector('[data-product-aed-purchase]');
    const productSaleInput = document.querySelector('[data-product-aed-sale]');
    const purchaseAed = document.querySelector('[data-purchase-aed]');
    const saleAed = document.querySelector('[data-sale-aed]');
    const cartonLengthInput = document.querySelector('[data-carton-length]');
    const cartonWidthInput = document.querySelector('[data-carton-width]');
    const cartonHeightInput = document.querySelector('[data-carton-height]');
    const cbmOutput = document.querySelector('[data-cbm-output]');
    const cbmHidden = document.querySelector('[data-cbm-hidden]');

    const buildQrUrl = (value) => {
        const payload = encodeURIComponent(value || 'QR');
        return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=0&data=${payload}`;
    };

    const refreshQr = () => {
        if (!productCodeInput || !qrPreview || !qrValue) {
            return;
        }

        const value = productCodeInput.value.trim() || 'QR';
        qrPreview.src = buildQrUrl(value);
        qrValue.textContent = value;
    };

    const refreshProductAedPreview = () => {
        if (!productPurchaseInput || !productSaleInput || !purchaseAed || !saleAed) {
            return;
        }

        purchaseAed.textContent = Number(productPurchaseInput.value || 0).toFixed(2);
        saleAed.textContent = Number(productSaleInput.value || 0).toFixed(2);
    };

    const refreshProductCbm = () => {
        if (!cartonLengthInput || !cartonWidthInput || !cartonHeightInput || !cbmOutput || !cbmHidden) {
            return;
        }

        const length = Number(cartonLengthInput.value || 0);
        const width = Number(cartonWidthInput.value || 0);
        const height = Number(cartonHeightInput.value || 0);
        const cbm = length > 0 && width > 0 && height > 0
            ? (length * width * height) / 1000000
            : 0;
        const formatted = cbm.toFixed(6);
        cbmOutput.value = formatted;
        cbmHidden.value = formatted;
    };

    if (productCodeInput) {
        productCodeInput.addEventListener('input', refreshQr);
        refreshQr();
    }

    if (productPurchaseInput && productSaleInput) {
        productPurchaseInput.addEventListener('input', refreshProductAedPreview);
        productSaleInput.addEventListener('input', refreshProductAedPreview);
        refreshProductAedPreview();
    }

    if (cartonLengthInput && cartonWidthInput && cartonHeightInput) {
        [cartonLengthInput, cartonWidthInput, cartonHeightInput].forEach((input) => {
            input.addEventListener('input', refreshProductCbm);
        });
        refreshProductCbm();
    }

    const productTypeSelect = document.querySelector('[data-item-type-select]');
    const inventoryOnlyCards = Array.from(document.querySelectorAll('[data-inventory-only-card]'));

    const refreshProductTypeUi = () => {
        if (!productTypeSelect) {
            return;
        }

        const isInventory = productTypeSelect.value === 'inventory';
        inventoryOnlyCards.forEach((card) => {
            card.classList.toggle('is-hidden', !isInventory);
        });

        if (!isInventory) {
            if (cartonLengthInput) cartonLengthInput.value = '0';
            if (cartonWidthInput) cartonWidthInput.value = '0';
            if (cartonHeightInput) cartonHeightInput.value = '0';
            const unitsPerBoxInput = document.querySelector('[data-units-per-box-master]');
            if (unitsPerBoxInput) unitsPerBoxInput.value = '1';
            refreshProductCbm();
        }
    };

    if (productTypeSelect) {
        productTypeSelect.addEventListener('change', refreshProductTypeUi);
        refreshProductTypeUi();
    }

    const customerTypeRadios = Array.from(document.querySelectorAll('[data-customer-type]'));
    const businessPanel = document.querySelector('[data-type-panel="business"]');
    const individualPanel = document.querySelector('[data-type-panel="individual"]');
    const businessInput = document.querySelector('[data-type-input="business"]');
    const individualInput = document.querySelector('[data-type-input="individual"]');
    const customerMobileInput = document.querySelector('[data-customer-mobile]');
    const countryCodeInput = document.querySelector('[data-country-code]');
    const countryNameInput = document.querySelector('[data-country-name]');
    const countryDisplayInput = document.querySelector('[data-country-display]');
    const countryChip = document.querySelector('[data-country-chip]');

    const countryDialMap = {
        '+971': { code: 'AE', name: 'United Arab Emirates' },
        '+966': { code: 'SA', name: 'Saudi Arabia' },
        '+973': { code: 'BH', name: 'Bahrain' },
        '+974': { code: 'QA', name: 'Qatar' },
        '+968': { code: 'OM', name: 'Oman' },
        '+965': { code: 'KW', name: 'Kuwait' },
        '+964': { code: 'IQ', name: 'Iraq' },
        '+962': { code: 'JO', name: 'Jordan' },
        '+961': { code: 'LB', name: 'Lebanon' },
        '+90': { code: 'TR', name: 'Turkey' },
        '+98': { code: 'IR', name: 'Iran' },
        '+44': { code: 'GB', name: 'United Kingdom' },
        '+1': { code: 'US', name: 'United States / Canada' },
        '+91': { code: 'IN', name: 'India' },
        '+92': { code: 'PK', name: 'Pakistan' },
        '+20': { code: 'EG', name: 'Egypt' },
        '+61': { code: 'AU', name: 'Australia' },
        '+49': { code: 'DE', name: 'Germany' },
        '+33': { code: 'FR', name: 'France' },
        '+39': { code: 'IT', name: 'Italy' },
        '+34': { code: 'ES', name: 'Spain' },
        '+7': { code: 'RU', name: 'Russia / Kazakhstan' },
        '+60': { code: 'MY', name: 'Malaysia' },
        '+65': { code: 'SG', name: 'Singapore' }
    };

    const normalizeMobileValue = (rawValue) => {
        let value = String(rawValue || '').trim();
        if (value === '') return '';
        value = value.replace(/\s+/g, ' ');
        if (!value.startsWith('+') && /^\d+/.test(value)) {
            if (value.startsWith('00')) {
                value = '+' + value.substring(2);
            } else {
                value = '+' + value;
            }
        }
        return value;
    };

    const detectCountryFromMobile = (rawValue) => {
        const normalized = normalizeMobileValue(rawValue).replace(/[^0-9+]/g, '');
        const codes = Object.keys(countryDialMap).sort((a, b) => b.length - a.length);

        for (const code of codes) {
            if (normalized.startsWith(code)) {
                return { dial: code, ...countryDialMap[code] };
            }
        }

        return null;
    };

    const refreshCustomerTypeUi = () => {
        if (!customerTypeRadios.length) {
            return;
        }

        const selected = customerTypeRadios.find((radio) => radio.checked)?.value || 'individual';
        customerTypeRadios.forEach((radio) => {
            radio.closest('.type-option')?.classList.toggle('is-active', radio.checked);
        });

        businessPanel?.classList.toggle('is-hidden', selected !== 'business');
        individualPanel?.classList.toggle('is-hidden', selected !== 'individual');

        if (businessInput) businessInput.disabled = selected !== 'business';
        if (individualInput) individualInput.disabled = selected !== 'individual';
    };

    const refreshCustomerCountry = () => {
        if (!customerMobileInput) {
            return;
        }

        const normalizedValue = normalizeMobileValue(customerMobileInput.value);
        const result = detectCountryFromMobile(normalizedValue);

        if (customerMobileInput.value !== normalizedValue && document.activeElement !== customerMobileInput) {
            customerMobileInput.value = normalizedValue;
        }

        if (!result) {
            if (countryCodeInput) countryCodeInput.value = '';
            if (countryNameInput) countryNameInput.value = '';
            if (countryDisplayInput) countryDisplayInput.value = '';
            if (countryChip) countryChip.textContent = 'Country will be detected from the + code';
            return;
        }

        if (countryCodeInput) countryCodeInput.value = result.code;
        if (countryNameInput) countryNameInput.value = result.name;
        if (countryDisplayInput) countryDisplayInput.value = result.name;
        if (countryChip) countryChip.textContent = `${result.code} · ${result.name} (${result.dial})`;
    };

    if (customerTypeRadios.length) {
        customerTypeRadios.forEach((radio) => radio.addEventListener('change', refreshCustomerTypeUi));
        refreshCustomerTypeUi();
    }

    if (customerMobileInput) {
        ['input', 'change', 'keyup', 'blur', 'paste'].forEach((eventName) => {
            customerMobileInput.addEventListener(eventName, () => {
                window.requestAnimationFrame(refreshCustomerCountry);
            });
        });
        refreshCustomerCountry();
        window.setTimeout(refreshCustomerCountry, 80);
    }
})();
