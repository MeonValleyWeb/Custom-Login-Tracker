/**
 * Custom Login Tracker Admin JavaScript
 */
jQuery(document).ready(function($) {
    
    // Initialize datepickers
    $('.date-picker').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });
    
    // User agent toggle
    $(document).on('click', '.show-more', function() {
        $(this).closest('.user-agent-truncated').hide();
        $(this).closest('td').find('.user-agent-full').show();
    });
    
    $(document).on('click', '.show-less', function() {
        $(this).closest('.user-agent-full').hide();
        $(this).closest('td').find('.user-agent-truncated').show();
    });
    
    // Confirm delete actions
    $('.delete-record').on('click', function(e) {
        if (!confirm(customLoginTracker.confirm_delete)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Toggle advanced filters
    $('.toggle-advanced-filters').on('click', function(e) {
        e.preventDefault();
        $('.advanced-filters').toggle();
        
        var $icon = $(this).find('.dashicons');
        if ($icon.hasClass('dashicons-arrow-down')) {
            $icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
            $(this).find('.filter-text').text(customLoginTracker.hide_advanced);
        } else {
            $icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
            $(this).find('.filter-text').text(customLoginTracker.show_advanced);
        }
    });
    
    // Quick filter buttons
    $('.quick-filter').on('click', function(e) {
        e.preventDefault();
        
        var days = $(this).data('days');
        var today = new Date();
        var fromDate = new Date();
        
        fromDate.setDate(today.getDate() - days);
        
        var $form = $(this).closest('form');
        $form.find('input[name="date_from"]').val(formatDate(fromDate));
        $form.find('input[name="date_to"]').val(formatDate(today));
        $form.submit();
    });
    
    // Helper function to format date as YYYY-MM-DD
    function formatDate(date) {
        var d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();
        
        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;
        
        return [year, month, day].join('-');
    }
});