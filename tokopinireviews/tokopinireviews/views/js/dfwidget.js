var current_page = 1;
var records_per_page = 5;

function prevPage(objJson)
{
	if (current_page > 1) {
		current_page--;
		changePage(current_page, objJson);
	}
}

function nextPage(objJson)
{
	if (current_page < numPages(objJson)) {
		current_page++;
		changePage(current_page, objJson);
	}
}
	
function changePage(page, objJson)
{
	if(objJson) {
		var btn_next = document.getElementById("btn_next");
		var btn_prev = document.getElementById("btn_prev");
		var comments = document.getElementById("reviewComments");
		var page_span = document.getElementById("page");
	 
		// Validate page
		if (page < 1) page = 1;
		if (page > numPages(objJson)) page = numPages(objJson);

		comments.innerHTML = "";

		for (var i = (page-1) * records_per_page; i < (page * records_per_page) && i < objJson.length; i++) {
			var created_at = objJson[i].tokopiniReview.created_at;
			var name = objJson[i].tokopiniReview.name;
			var puntuation = objJson[i].tokopiniReview.puntuation.average / 2;
			var comment = objJson[i].tokopiniReview.comment;
			if(puntuation !=="") {
				var starrating = (puntuation / 10) * 2 * 100 + "%";
			} else {
				var starrating = "0%";
			}
			comments.innerHTML += "<div id='feedback_review_detail'><div id='feedback_review_avg'>Review Rating: " + puntuation + " / 5</div><div class='star-ratings-sprite'><span style='width:"+ starrating +"' class='star-ratings-sprite-rating'></span></div><div id='feedback_comment'>" + comment + "</div><div id='feedback_created_at'>" + created_at + "</div><div id='feedback_name'>By " + name + "</div></div>";
		}
		page_span.innerHTML = page + "/" + numPages(objJson);

		if (page == 1) {
			btn_prev.style.visibility = "hidden";
		} else {
			btn_prev.style.visibility = "visible";
		}

		if (page == numPages(objJson)) {
			btn_next.style.visibility = "hidden";
		} else {
			btn_next.style.visibility = "visible";
		}
	}
}

function numPages(objJson)
{
	if(objJson) {
		return Math.ceil(objJson.length / records_per_page);
	} else {
		return 0;
	}
}
window.onload = function(){
	// Modal button
	var openModal = document.getElementById('open-modal');
	
	// Modal 1 ID
	var modal = document.getElementById('modal-1');
	
	// Close modal button
	var closeModal = document.getElementsByClassName('close-modal')[0];
	
	// Open modal event listener
	openModal.addEventListener('click', function(){
		modal.classList.toggle('visible');
	});
	
	// Close modal event listener
	closeModal.addEventListener('click', function(){
		modal.classList.remove('visible');
	});
}