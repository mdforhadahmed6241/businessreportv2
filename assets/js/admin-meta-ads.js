jQuery(document).ready(function($) {
    'use strict';

    // --- Reusable Feedback Function ---
    function showFeedback(message, isError = false) {
        const feedbackEl = $('#br-sync-feedback');
        feedbackEl.text(message).removeClass('error success').addClass(isError ? 'error' : 'success').fadeIn();
        setTimeout(() => feedbackEl.fadeOut(), 5000);
    }
    
    // --- Date Filter Dropdown ---
    $('.br-dropdown-toggle').on('click', function(e) { e.preventDefault(); $(this).next('.br-dropdown-menu').fadeToggle(100); });
    $(document).on('click', function(e) { if (!$(e.target).closest('.br-dropdown').length) { $('.br-dropdown-menu').fadeOut(100); } });

    // --- Modal Handling ---
    const addAccountModal = $('#br-add-account-modal');
    const customSyncModal = $('#br-custom-sync-modal');
    function openAddAccountModal(accountId = '') {
        $('#br-add-account-form')[0].reset(); $('#account_id').val(''); $('#br-modal-title').text('Add Meta Ads Account'); $('.button-primary').text('Save Account');
        if (accountId) {
            $('#br-modal-title').text('Edit Meta Ads Account'); $('.button-primary').text('Update Account');
            $.post(br_meta_ads_ajax.ajax_url, { action: 'br_get_meta_account_details', nonce: br_meta_ads_ajax.nonce, account_id: accountId })
            .done(function(response) {
                if(response.success) {
                    const acc = response.data;
                    $('#account_id').val(acc.id); $('#account_name').val(acc.account_name); $('#app_id').val(acc.app_id); $('#ad_account_id').val(acc.ad_account_id);
                    $('#usd_to_bdt_rate').val(acc.usd_to_bdt_rate); $('#is_active').prop('checked', acc.is_active == 1);
                } else { alert(response.data.message); }
            });
        }
        addAccountModal.fadeIn(200);
    }
    function closeAddAccountModal() { addAccountModal.fadeOut(200); }
    function openCustomSyncModal() { customSyncModal.fadeIn(200); }
    function closeCustomSyncModal() { customSyncModal.fadeOut(200); }
    $('#br-add-account-btn').on('click', () => openAddAccountModal());
    $('#br-ad-accounts-list').on('click', '.br-edit-account-btn', function(e){ e.preventDefault(); openAddAccountModal($(this).closest('.br-ad-account-card').data('account-id')); });
    addAccountModal.find('.br-modal-close, .br-modal-cancel').on('click', closeAddAccountModal);
    $('#br-custom-sync-btn').on('click', openCustomSyncModal);
    customSyncModal.find('.br-modal-close, .br-modal-cancel').on('click', closeCustomSyncModal);
    $(window).on('click', function(e) { 
        if ($(e.target).is(addAccountModal)) { closeAddAccountModal(); }
        if ($(e.target).is(customSyncModal)) { closeCustomSyncModal(); }
    });
    $('.br-datepicker').datepicker({ dateFormat: 'yy-mm-dd' });
    $('#br-select-all-accounts').on('click', function() { $('#br-custom-sync-form .br-checklist input[type="checkbox"]').prop('checked', true); });
    $('#br-deselect-all-accounts').on('click', function() { $('#br-custom-sync-form .br-checklist input[type="checkbox"]').prop('checked', false); });

    // --- CRUD and Actions ---
    $('#br-add-account-form').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serializeArray().reduce((obj, item) => { obj[item.name] = item.value; return obj; }, {});
        formData.action = 'br_save_meta_account'; formData.nonce = br_meta_ads_ajax.nonce; formData.is_active = $('#is_active').is(':checked');
        $.post(br_meta_ads_ajax.ajax_url, formData).done(function(response) { alert(response.data.message); if(response.success) { window.location.reload(); } });
    });
    $('#br-ad-accounts-list').on('click', '.br-delete-account-btn', function(e){ e.preventDefault(); if (!confirm('Are you sure?')) return; $.post(br_meta_ads_ajax.ajax_url, { action: 'br_delete_meta_account', nonce: br_meta_ads_ajax.nonce, account_id: $(this).closest('.br-ad-account-card').data('account-id') }).done(function(response){ alert(response.data.message); if(response.success) { window.location.reload(); } }); });
    $('#br-ad-accounts-list').on('change', '.br-status-toggle', function(){ $.post(br_meta_ads_ajax.ajax_url, { action: 'br_toggle_account_status', nonce: br_meta_ads_ajax.nonce, account_id: $(this).closest('.br-ad-account-card').data('account-id'), is_active: $(this).is(':checked') }); });
    $('#br-ad-accounts-list').on('click', '.br-test-connection-btn', function(){ alert('Testing connection...'); });
    $('.br-data-table-wrapper').on('click', '.br-delete-summary-btn', function(e) { e.preventDefault(); if (!confirm('Are you sure?')) return; const btn = $(this); btn.prop('disabled', true); $.post(br_meta_ads_ajax.ajax_url, { action: 'br_delete_summary_entry', nonce: br_meta_ads_ajax.nonce, entry_id: $(this).data('id') }).done(function(response) { if (response.success) { window.location.reload(); } else { alert('Error: ' + response.data.message); btn.prop('disabled', false); } }).fail(function() { alert('An error occurred.'); btn.prop('disabled', false); }); });

    // --- Sync Logic ---
    function triggerSync(data, btn) {
        const spinner = $('#br-sync-spinner');
        btn.prop('disabled', true).siblings('button').prop('disabled', true); spinner.addClass('is-active');
        $('#br-sync-feedback').text('Syncing...').removeClass('error success').fadeIn();
        data.action = 'br_sync_meta_data'; data.nonce = br_meta_ads_ajax.nonce;
        $.post(br_meta_ads_ajax.ajax_url, data)
        .done(function(response) {
            if (response.success) { showFeedback(response.data.message, false); setTimeout(() => window.location.reload(), 2000); } 
            else { showFeedback(response.data.message, true); btn.prop('disabled', false).siblings('button').prop('disabled', false); }
        })
        .fail(function() { showFeedback('An error occurred during sync.', true); btn.prop('disabled', false).siblings('button').prop('disabled', false); })
        .always(function() { spinner.removeClass('is-active'); });
    }

    // **FIX:** Use a reliable method to get the local date string, avoiding timezone conversion issues.
    function getLocalDateString(date) {
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    $('#br-sync-today-btn').on('click', function() {
        const today = getLocalDateString(new Date());
        triggerSync({ start_date: today, end_date: today }, $(this));
    });
    
    $('#br-sync-7-days-btn').on('click', function() {
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(endDate.getDate() - 6);
        triggerSync({ 
            start_date: getLocalDateString(startDate), 
            end_date: getLocalDateString(endDate) 
        }, $(this));
    });

    $('#br-custom-sync-form').on('submit', function(e) {
        e.preventDefault();
        const data = {
            start_date: $('#sync_start_date').val(),
            end_date: $('#sync_end_date').val(),
            account_ids: $('input[name="account_ids[]"]:checked').map(function(){ return $(this).val(); }).get()
        };
        if (!data.start_date || !data.end_date) { alert('Please select a start and end date.'); return; }
        if (data.account_ids.length === 0) { alert('Please select at least one account to sync.'); return; }
        triggerSync(data, $(this).find('.button-primary'));
    });
});

