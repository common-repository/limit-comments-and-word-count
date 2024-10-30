(function ($) {
    $(document).ready(function () {
        $('#comment').on('keyup', function (e) {
            var $ctx = $(e.currentTarget);
            var text = $ctx.val().trim();

            updateTextAreaCounters(text, $ctx);
        });

        function updateTextAreaCounters(text, $ctx){
            var value = countWords(text);
            var max = $ctx.attr('data-max');
            if (max) {
                if (value >= max) {
                    $( ".comment-max" ).addClass( "max-reached" )
                    $ctx.removeClass('low-length').addClass('large-length');
                }
                else {
                    $ctx.removeClass('large-length');
                    $( ".comment-max" ).removeClass( "max-reached" )
                    lpwc_hide_limit_message();
                }
            }
            $('#words_count').text(value);
            $('#chars_count').text($ctx.val().length);
        }

        function countWords(text){
            if(text.length > 0)
                return text.split(" ").length;
            return 0;
        }

        $('#comment').bind('paste', null, function(e){
            var $ctx = $(e.currentTarget);
            setTimeout(function() {
                var text = $ctx.val().trim();
                updateTextAreaCounters(text, $ctx);
            }, 100);
        });

        $('#comment').bind('cut', null, function(e){
            var $ctx = $(e.currentTarget);
            setTimeout(function() {
                var text = $ctx.val().trim();
                updateTextAreaCounters(text, $ctx);
            }, 100);
        });

        $('#commentform').on('submit', function(e){
            var $textarea = $('#comment');
            var min = $textarea.attr('data-min');
            var value = this.comment.value;
            var isCorrect = true;
            var max = $textarea.attr('data-max');
            if (max) {
                if (countWords(value) > max) {
                    isCorrect = false;
                }
            }
            if(value.length === 0){
                isCorrect = false
            }
            if(!isCorrect){
                e.preventDefault();
                lpwc_show_limit_message();
            }
        });
    });

    function lpwc_show_limit_message(){
        $('.alert-message').show();
    }

    function lpwc_hide_limit_message(){
        $('.alert-message').hide();
    }
    
   // Display comment rule modal
   $(document).on("click", ".lpwc-comment-rules", function () {
     $("#lpwc-comment-rules-overlay").lpwcmodal({
         onShow: function (dlg) {
            $("#simplemodal-container").css({
               "max-height": "90%",
               "top":"20%",
               "z-index":"10011"
            });
            $(".simplemodal-container").css({
               "height": "500px",
               "z-index":"10011"
            });
            $(dlg.wrap).css('overflow', 'auto'); 
         }
      });       
    });
})(jQuery);