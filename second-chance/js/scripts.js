$ = jQuery;
$(document).ready(function() {
    $(".sc_link").on("click", function() {
        $('.sc_tabs').hide();
        $(this.getAttribute("href")).show();
        return false;
    });

    $('.status').change(function() {
        var h = $(this).val();
        var $row = $(this).closest("tr");
        var uid = $row.find(".nr1").text();
        var pid = $row.find(".nr2").text();
        $.ajax({
            data: {action: 'update-row',status: h, uid: uid, pid: pid},
            type: 'post',
            success: function() {
                window.location.reload();
            }});
    });
    if($('div').is('.sc-content')) {
        $('#right-sidebar').hide();
        $('#content').width('100%');
    }
});