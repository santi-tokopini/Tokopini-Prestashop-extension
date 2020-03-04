{* 2019 TokoPini
*
*  @author TokoPini <support@tokoini.com>
*  @copyright  2019 TokoPini
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<div class="tokopinireviews-reviews-widget">
	<script language="javascript"> 
	window.addEventListener('load', function() {
		var objJson = {$data nofilter}; 
		changePage(1, objJson);
	})
	</script>
</div>
<div class="stickysidebar" id="open-modal" data-triger="modal-1">
    <p>{$tabContent nofilter}</p>
</div>
<div class="modal-wrap" id="modal-1">
	<button class="close-modal">&times;</button>
	<div class="modal-container">
	  <div class="modal-content">
	  	<h3>Customer Reviews</h3>
	    <div id="reviewComments"></div>
	    <div class="feedback_pager">
			<a href="javascript:prevPage(objJson)" id="btn_prev">Prev</a>
			<span id="page"></span>
			<a href="javascript:nextPage(objJson)" id="btn_next">Next</a>
		</div>
	  </div>
	</div>
</div>
