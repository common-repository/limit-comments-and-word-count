<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
   exit();
}

?>
<div class="lpwc-comment-rules-modal lpwc-hidden" id="lpwc-comment-rules-overlay">
   <div class="lpwc-dialog-title"><strong><?php echo __( "Comment Rules", "limit-comments-and-word-count" ); ?></strong></div>
   <div>
      <div class="select-part lpwc-wrap">
         <p>
            <?php
            $comments_rules = get_option( 'lpwc_comment_rules' );
            echo stripslashes( $comments_rules );
            ?>
         </p>         
      </div>
   </div>
</div>