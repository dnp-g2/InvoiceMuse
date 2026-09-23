<script>
    var delay = (function () {
        var timer = 0;
        return function (callback, ms) {
            clearTimeout(timer);
            timer = setTimeout(callback, ms);
        };
    })();

    $(function () {
        $('#filter').keyup(function () {
            delay(function () {
                $.post('<?php echo site_url('filter/ajax/' . $filter_method); ?>',
                    {
                        filter_query: $('#filter').val(),
                        filter_status: <?php echo json_encode($filter_status ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                    }, function (data) {
                        <?php echo IP_DEBUG ? 'console.log(data);' : ''; ?>
                        $('#filter_results').html(data);
                    });
            }, 1000);
        });
    });
</script>