(function($){
    /*$('.navbar-side .menu-item-has-children > a').on('click', function(e){
        e.preventDefault();
        $(this).parent().find('.dropdown-menu').toggleClass('show');
    });*/
	
	/* new mobile menu
	 * Chris D.
	 * 5 July 2021
	 */	
	$('.navbar-side .menu-item-has-children').each(function(){
		var toggleBtn = $('<span class="menu-toggle dropdown-toggle"></span>').prependTo(this),
			dropElement = $('> .dropdown-menu',this);
		
			toggleBtn.click(function() {
				if(dropElement.is(':visible')) {
					dropElement.toggleClass('show');
					$(this).removeClass('drop');
					$(this).parent().removeClass('open');
				}else{
					dropElement.toggleClass('show');
					$(this).addClass('drop');
					$(this).parent().addClass('open');
				}
			});
	});
}(jQuery));