var counter = 0;
jQuery(document).ready(function(){
    counter = jQuery('#limit_rules  tbody tr').length;

    jQuery(document).on('click', '#lpwc-trial-message .notice-dismiss', function(e){
        e.preventDefault();
        jQuery.ajax({
            method: 'POST',
            url: window.ajaxurl + "?action=lpwc_cancel_notification",
        });
    });
    
    jQuery(document).on('click', '#lpwc-feature-message .notice-dismiss', function(e){
        e.preventDefault();
        jQuery.ajax({
            method: 'POST',
            url: window.ajaxurl + "?action=lpwc_close_feature_notification",
        });
    });

});
var curr_row;
function res_form(){
    jQuery('#ur').val(data.editor);
    jQuery('#pt').val(data.any);
    jQuery('#lim').val('');
    jQuery('#comment_word_limit_ms').val('');
    jQuery('#comment_limit_max').val('');
    jQuery('#st').val('any');
    jQuery('#ms').val('');
    jQuery('.ro2').val('');
    jQuery('#rule_count').val('');
    jQuery('.save_edit').removeClass('save_edit').addClass('new_rule');
    jQuery('.new_rule').val(data.new_rule_caption);
    jQuery('.ro').show();
    jQuery('.user_i').hide();
    jQuery('#tis').val(data.day);
}

function add_new(){
    counter++;
    var tr = jQuery('<tr>');
    v1 = jQuery('#ur').val();
    if (v1 === 'USER_ID'){
        v1 = jQuery('#ro2').val();
    }
    v2 = jQuery('#pt').val();
    v3 = jQuery('#lim').val();
    v4 = jQuery('#st').val();
    v5 = jQuery('#tis').val();
    v6 = jQuery('#comment_limit_max').val();
    v8 = jQuery('#ms').val();
    v9 = jQuery('#comment_word_limit_ms').val();
    tr.append('<td>'+ v1 +' <input type="hidden" name="lpwc[rules][' + counter + '][role]" value="'+v1+'"></td>'); //<th>User Role/ID</th>
    tr.append('<td>'+ v2 +' <input type="hidden" name="lpwc[rules][' + counter + '][post_type]" value="'+v2+'"></td>'); //<th>Post Type</th>
    tr.append('<td>'+ v3 +' <input type="hidden" name="lpwc[rules][' + counter + '][limit]" value="'+v3+'"></td>'); //<th>Limit</th>
    tr.append('<td>'+ v4 +' <input type="hidden" name="lpwc[rules][' + counter + '][status]" value="'+v4+'"></td>'); //<th>Post Status</th>
    tr.append('<td>'+lpwc_get_frequency_label(v5)+' <input type="hidden" name="lpwc[rules][' + counter + '][time_span]" value="'+v5+'"></td>');
    tr.append('<td>'+ v6 +' <input type="hidden" name="lpwc[rules][' + counter + '][comment_limit_max]" value="'+v6+'"></td>'); //<th>Comment Limit</th>
    tr.append('<td><pre><code>'+ lpwc_wrap_text(html_entities(v8), 2) +'</code></pre> <input type="hidden" name="lpwc[rules][' + counter + '][message]" value="'+v8+'"/></td>'); //<th>Limit message</th>tr.append('<td><span class="edit_rule button-primary">Edit</span> <span class="remove_rule button-primary">Remove</span></td>'); //<th>Actions</th>
    tr.append('<td><pre><code>' + lpwc_wrap_text(html_entities(v9), 2) + '</code></pre>' +
        '<input type="hidden" name="lpwc[rules][' + counter + '][comment_word_limit_ms]" value="' + v9 + '"/>');
    tr.append('<td><span class="edit_rule flat-button"><img src="' + data.edit_icon + '" alt="edit" title="'+ data.edit + '"/> </span> <span class="remove_rule flat-button"><img src="' + data.trash_icon + '" alt="remove" title="' + data.remove + '"/></span>'); //<th>Actions</th>
    jQuery('#limit_rules').find('tbody').append(tr);
    saveResults();
}

function lpwc_wrap_text(text, linesCount){
    var len = text.length;
    if(len > 10) {
        var result = '';
        var pieceLen = len / linesCount;
        var start = 0;
        for (var i = 0; i < linesCount - 1; i++) {
            var stop = (i + 1) * pieceLen;
            stop = text.substring(start, stop).lastIndexOf(" ");
            var subtext = text.substring(start, stop) + '\r\n';
            if(i > 0){
                subtext = " " + subtext;
            }
            result += subtext;
            start = ++stop;
        }
        var subtext = text.substring(start);
        if(i === 1){
            subtext = " " + subtext;
        }
        result += subtext;
        return result;
    }
    return text;
}

//load edit
function pre_edit_form(){
    res_form();
    v = [];
    var $newRule = jQuery('.new_rule');
    $newRule.val(data.update_table_caption);
    $newRule.removeClass('new_rule').addClass('save_edit');
    jQuery(curr_row).find('input').each(function(index, value) {
        v[index] = jQuery(value).val();
    });
    if (jQuery.isNumeric(v[0])){
        jQuery('#ur').val('USER_ID');
        jQuery('#ro2').val(v[0]);
        jQuery('.ro').hide();
        jQuery('.user_i').show();
    }else{
        jQuery('#ur').val(v[0]);
    }
    jQuery('#pt').val(v[1]);
    jQuery('#lim').val(v[2]);
    jQuery('#st').val(v[3]);
    jQuery('#tis').val(v[4]);
    jQuery('#comment_limit_max').val(v[5]);
    jQuery('#ms').val(v[6]);
    jQuery('#comment_word_limit_ms').val(v[7]);
}

//save edit function
function r_save_edit(){
    var v = [];
    v[0] = jQuery('#ur').val();
    if (v[0] === 'USER_ID'){
        v[0] = jQuery('#ro2').val();
    }
    v[1] = jQuery('#pt').val();
    v[2] = jQuery('#lim').val();
    v[3] = jQuery('#st').val();
    v[4] = jQuery('#tis').val();
    v[5] = jQuery('#comment_limit_max').val();
    v[6] = jQuery('#ms').val();
    v[7] = jQuery('#comment_word_limit_ms').val();
    var $currRow = jQuery(curr_row);
    $currRow.find('td').each(function(index, td){
        var $td = jQuery(td);
        var $input = $td.find('input').clone();
        $input.val(v[index]);
        if(index < v.length - 2) {
            $td.text(v[index]);
        }
        $td.append($input);
    });
    $currRow.find('#message pre > code').text(lpwc_wrap_text(v[6], 2));
    $currRow.find('#comment_word_limit_ms pre > code').text(lpwc_wrap_text(v[7], 2));
    saveResults();
}

function saveResults(){
    jQuery('#rules_form').submit();
}

function lpwc_get_frequency_label(val){
    var result = '';
    switch(val){
        case 'day':
            result = data.per_day;
            break;
        case 'week':
            result = data.per_week;
            break;
        case 'month':
            result = data.per_month;
            break;
		case 'year':
			result = data.per_year;
		break;
    }
    return result;
}
//htmlentities
function html_entities(str){
    encoded = jQuery('<div />').text(str).html();
    return encoded;
}
function isNumeric(n) {
    return !isNaN(parseFloat(n)) && isFinite(n);
}
//add new rule
jQuery(document).on('click','.new_rule',function(e){
    e.preventDefault();
    add_new();
});
//remove rule
jQuery(document).on('click','.remove_rule',function(e){
    e.preventDefault();
    jQuery(this).parent().parent().remove();
    saveResults();
});
//row edit
jQuery(document).on('click','.edit_rule',function(e){
    e.preventDefault();
    curr_row = jQuery(this).parent().parent();
    pre_edit_form();
});
//save edit
jQuery(document).on('click','.save_edit',function(e){
    e.preventDefault();
    r_save_edit();
    res_form();
});

//user id
jQuery(document).on('change','#ur',function() {
    var $ro = jQuery('.ro');
    var $ur = jQuery('#ur');
    var $useri = jQuery('.user_i');
    if ($ur.val() === 'USER_ID'){
        $ro.hide();
        $useri.show();
    }
    else{
        $useri.hide();
        $ro.show();
    }
});

