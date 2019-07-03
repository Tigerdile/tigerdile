/**
 * An implementation of an extremely simple table sort designed
 * to work with jQuery and a low number of rows (i.e. it does no
 * fancypants caching or internal storage).
 *
 * Tailored to work with Tigerdile's new stream page.
 * https://www.tigerdile.com
 *
 * Released in Public Domain.  Originally written by
 * Tigerdile, LLC
 */


jQuery.fn.extend({
	/**
	 * Only works with <table> elements.
         * Can work with multiple table elements.
         */
	tablesort: function() {
		return this.each(function() {
			// Only tables
			if(this.nodeName != 'TABLE') {
				// Ignore it
				return true;
			}

			var tableJQ = jQuery(this);

			// Convert each header to a link.
			var myColNum = 0;
			tableJQ.find('thead tr th').each(function() {
				var thHtml = '<a href="#" class="td-header-sort">' + jQuery(this).html() + '</a>';
				jQuery(this).html(thHtml);

				this["columnNumber"] = myColNum;
				myColNum++;

				// Add a sort method if we don't have one.
				if(!this["sortFunction"]) {
					this["sortFunction"] = function(a, b) {
						var textA = jQuery(a).find('td.sorted').text();
						var textB = jQuery(b).find('td.sorted').text();

						// try parseInt
						if((!isNaN(parseInt(textA))) && (!isNaN(parseInt(textB)))) {
							return (parseInt(textA) > parseInt(textB));
						} else {
							return (textA.localeCompare(textB));
						}
					};
				}
			});

			tableJQ.get(0)['resort'] = function(myTable) {
				// Make sure it's a jQuery
				myTable = jQuery(myTable);

				var trigger = myTable.find('thead tr th a.active');

				// Might be none -- abort
				if(!trigger.length) {
					return;
				}

				var desc = trigger.hasClass('desc');

				// Do the actual sort
				var rows = jQuery('tbody > tr', myTable);

				// set the sorted class
				rows.find('td').removeClass('sorted');
				rows.find('td:eq(' + trigger.parent().get(0).columnNumber +')').addClass('sorted');

				rows.sort(trigger.parent().get(0).sortFunction);

				jQuery.each(rows, function(index, row) {
					if(desc) {
						myTable.append(row);
					} else {
						myTable.prepend(row);
					}
				});
			};

			// Attach sort handler to each column, and a 'resort'
			// handler to the table itself.
			tableJQ.find('thead tr th a.td-header-sort').each(function() {
				jQuery(this).click(function(ev) {
					ev.preventDefault();
					var trigger = jQuery(this);
					var desc = false;

					// I may be active and flipping sort, or I may be a new sort.
					if(trigger.hasClass('active')) {
						var desc = trigger.hasClass('desc');

						if(desc) {
							trigger.removeClass('desc');
						} else {
							trigger.addClass('desc');
						}
					} else {
						trigger.parent().parent().find('th a').removeClass('active');
						trigger.addClass('active');
					}

					myTable = trigger.parent().parent().parent().parent();
					myTable.get(0).resort(myTable);

					return false;
				});
			});
		});
	}
});
