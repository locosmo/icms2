var icms = icms || {};

icms.datagrid = (function ($) {

	this.options = {};
    this.selected_rows = [];
    this.is_loading = true;
    this.callback = false;
	this.was_init = false;

    //====================================================================//

    this.setOptions = function(options){
        this.options = options;
    }

    //====================================================================//
	
    this.bind_sortable = function(){
        $('.datagrid th.sortable').click(function(){
			console.log('click');
            icms.datagrid.clickHeader($(this).attr('rel'));
        });
    };
    this.bind_filter = function(){
        $('.datagrid .filter .input').keypress(function(event){

            if (event.which == 13) {

                event.preventDefault();

                $('.datagrid .filter .input').each(function(){
                    var filter = $(this).attr('rel');
                    $('#datagrid_filter input[name='+filter+']').val($(this).val());
                });

                icms.datagrid.setPage(1);
                icms.datagrid.loadRows();

            }

        });
    };
	
    //====================================================================//

    this.init = function(){
		
        if(this.was_init){return false;}
        this.was_init = true;
		
        console.log('init');

        if (this.options.is_sortable){
            console.log('sortable');
			this.bind_sortable();
        } else {
            console.log('not sortable');
        }

        if (this.options.is_filter){
            this.bind_filter();
        }

        if (this.options.is_pagination){
            $('.datagrid_resize select').change(function(){
                icms.datagrid.setPage(1, $(this).val());
                icms.datagrid.loadRows();
            });
        }

        if (this.options.is_selectable){
            var ctrl = false, shift = false;
            var tbody = $('#datagrid > tbody');
            var last = tbody.find('> tr:not(.filter):first');
            $(document).keydown(function(event) {
                if(event.keyCode === 16){shift = true;$('#datagrid').disableSelection();}
                if(event.keyCode === 17){ctrl = true;$('#datagrid').disableSelection();}
                }).keyup(function(event){
                if(event.keyCode === 16){shift = false;$('#datagrid').enableSelection();}
                if(event.keyCode === 17){ctrl = false;$('#datagrid').enableSelection();}
            });
            $(document).on('click', '#datagrid > tbody > tr:not(.filter) > td', function(){
                var tr = $(this).parent();
                if(shift){
                    if(!last.size()){last = tbody.find('> tr:not(.filter):first').toggleClass('selected');}
                    var in1 = tbody.find('> tr:not(.filter)#'+tr.attr('id')).index();
                    var in2 = tbody.find('> tr:not(.filter)#'+last.attr('id')).index();
                    if(in1 === in2){
                        tr.toggleClass('selected');
                    }else{
                        tbody.find('> tr:not(.filter):gt('+((in1<in2 ? in1-2 : in2-1))+'):not(:gt('+((in1>in2 ? (in1-in2) : (in2-in1))-1)+'))').toggleClass('selected');
                    }
                }else{
                    tr.toggleClass('selected');
                }
                last = tr;
            });
        }
	
        this.setOrdering();
        
        if (this.options.url){
            this.loadRows();
        }

    }

    //====================================================================//

    this.submit = function(url, confirm_message){

        var selected_rows_count = this.selectedRowsCount();
        if (selected_rows_count == 0  && !this.options.is_draggable) {return false;}

        if (typeof(confirm_message) == 'string'){
            if (!confirm(confirm_message)){return false;}
        }

        if (typeof(url) != 'string') {url = $(url).data('url');}

        $('#datagrid_form').html('');
        $('#datagrid_form').attr('action', url);

        if (selected_rows_count > 0){
            $('.datagrid tbody tr.selected').each(function(){
                var row_id = $(this).attr('id');
                $('#datagrid_form').append('<input type="hidden" name="selected[]" value="'+row_id+'" />');
            })
        }

        if (this.options.is_draggable){
            $('.datagrid tbody tr').each(function(){
                var row_id = $(this).attr('id');
                $('#datagrid_form').append('<input type="hidden" name="items[]" value="'+row_id+'" />');
            })
        }

        $('#datagrid_form').submit();

        return false;

    }

    //====================================================================//

    this.submitAjax = function (url, confirm_message){

        var selected_rows_count = this.selectedRowsCount();
        if (selected_rows_count == 0) {return false;}

        if (typeof(confirm_message) == 'string'){
            if (!confirm(confirm_message)){return false;}
        }

        if (typeof(url) != 'string') {url = $(url).data('url');}

        this.selected_rows = [];
        $('.datagrid tr.selected').each(function(){
            icms.datagrid.selected_rows.push($(this).attr('id'));
        });

        icms.modal.openAjax(url, {selected: this.selected_rows});

        return false;

    }

    //====================================================================//

    this.selectedRowsCount = function(){

        if (!this.options.is_selectable) {return 0;}

        var selected_rows_count = $('.datagrid tr.selected').length;

        if (this.options.is_selectable && !selected_rows_count) {
            alert(LANG_LIST_NONE_SELECTED);
            return 0;
        }

        return selected_rows_count;

    }

    //====================================================================//

    this.clickHeader = function(name){

        if (this.options.order_by != name){
            this.options.order_to = 'asc';
        } else {
            this.options.order_to = this.options.order_to == 'desc' ? 'asc' : 'desc';
        }

        this.options.order_by = name;
        this.setOrdering();
        this.loadRows();

    }

    //====================================================================//

    this.setURL = function(url){
        this.options.url = url;
        this.setPage(1);
    }

    this.setOrdering = function(){
        if (!this.options.is_sortable) {return;}

        $('#datagrid_filter input[name=order_by]').val(this.options.order_by);
        $('#datagrid_filter input[name=order_to]').val(this.options.order_to);

        $('.datagrid th').removeClass('sort_asc').removeClass('sort_desc');
        $('.datagrid th[rel='+this.options.order_by+']').addClass('sort_'+this.options.order_to);
    }

    this.setPage = function(page, perpage){
        if (!this.options.is_pagination) {return;}

        this.options.page = page;
        if (typeof(perpage) != 'undefined'){this.options.perpage = perpage;}

        $('#datagrid_filter input[name=page]').val(this.options.page);
        $('#datagrid_filter input[name=perpage]').val(this.options.perpage);
    }

    //====================================================================//

    this.loadRows = function (callback){
		if(!this.was_init){return false;}

        this.is_loading = true;

        setTimeout(this.showLoadIndicator, 500);

        var filter_query = $('#datagrid_filter').serialize();

        var heads = [];
        $('#datagrid thead th[rel]').each(function(){
            heads.push($(this).attr('rel'));
        });

        $.post(this.options.url, {filter: filter_query, heads: heads}, function(result){
            icms.datagrid.rowsLoaded(result);
            if (typeof(callback) != 'undefined'){
                callback();
            }
        }, 'json');

    }

    //====================================================================//

    this.rowsLoaded = function(result){

        icms.datagrid.is_loading = false;

        icms.datagrid.hideLoadIndicator();

        $('.datagrid > tbody > tr:not(.filter)').remove();
        $('.datagrid_pagination').hide();

        if(result.columns.length){
            var htr = $('.datagrid > thead > tr:first');
            var ftr = $('.datagrid > tbody > tr.filter');
            htr.find('> th').remove();
            ftr.find('> td').remove();
            for(var key in result.columns)if(result.columns.hasOwnProperty(key)){
                htr.append('<th width="'+result.columns[key]['width']+'" rel="'+result.columns[key]['name']+'"'+(result.columns[key]['sortable'] ? ' class="sortable"' : '')+'>'+result.columns[key]['title']+'</th>');
                ftr.append('<td>'+(result.columns[key]['filter']||'&nbsp;')+'</td>');

                $('#datagrid_filter input[name="'+result.columns[key]['name']+'"]').remove();
                if(result.columns[key]['filter']){
                    $('#datagrid_filter').append('<input type="hidden" value="'+($(result.columns[key]['filter']).val()||'')+'" name="'+result.columns[key]['name']+'" />');
                }
            }
            icms.datagrid.bind_sortable();
            icms.datagrid.bind_filter();
        }

        if(!result.rows.length){
            var columns_count = $('.datagrid thead th').length;
            $('.datagrid tbody').append('<tr><td colspan="'+columns_count+'"><span class="empty">'+LANG_LIST_EMPTY+'</span></td></tr>');
            if (this.callback) { this.callback(); }
            return;
        }

        $.each(result.rows, function(){
            var row = this;
            var row_html = '<tr id="'+row[0]+'">';
            $.each(row, function(index){
                if (index>0 || icms.datagrid.options.show_id) {
                        row_html = row_html + '<td>' + this + '</td>';
                }
            });
            row_html = row_html + '</tr>';
            $('.datagrid tbody').append(row_html);
        });

        $('.datagrid tbody tr:odd').addClass('odd');

        if (icms.datagrid.options.is_draggable) {
            $('#datagrid').tableDnD({
                onDragClass: 'dragged',
                onDrop: function(table, row) {
                    $('.datagrid tbody tr').removeClass('odd');
                    $('.datagrid tbody tr:odd').addClass('odd');
                }
            });
        }

        if (icms.datagrid.options.is_pagination && result.pages_count > 1) {
            $('.datagrid_pagination').show();
            if (result.pages_count != icms.datagrid.options.pages_count){

                $('.datagrid_pagination').paginate({
                            count 		: result.pages_count,
                            start 		: 1,
                            display     : 7,
                            border					: false,
                            images					: false,
                            rotate                  : false,
                            mouse					: 'press',
                            border_color  			: '#fff',
                            text_color  			: '#333',
                            background_color    	: '#fff',
                            border_hover_color		: '#7d929d',
                            text_hover_color  		: '#fff',
                            background_hover_color	: '#7d929d',
                            onChange     			: function(page){
                                icms.datagrid.setPage(page);
                                icms.datagrid.loadRows();
                            }
                });

            }
        }

        icms.datagrid.options.pages_count = result.pages_count;

		$('.datagrid .flag_trigger a').on('click', function(){
		
			var url = $(this).attr('href');
			var link = $(this);

			link.parent('.flag_trigger').addClass('loading');
			
			$.post(url, {}, function(result){
				
				var flag = link.parent('.flag_trigger').removeClass('loading');
				if (result.error){ return; }

				var flag_class = flag.data('class');
				var flag_class_on = flag_class + '_on';
				var flag_class_off = flag_class + '_off';
				
				if (result.is_on){
					flag.removeClass(flag_class_off).addClass(flag_class_on);
				} else {
					flag.removeClass(flag_class_on).addClass(flag_class_off);
				}
				
			}, 'json');
			
			return false;
			
		});

        if (icms.datagrid.callback) { icms.datagrid.callback(); }

    }

    //====================================================================//

    this.showLoadIndicator = function(){
        if (!this.is_loading) {return;}

        $('.datagrid_loading').show();

        var pos = $('.datagrid tbody').offset();
        var w = $('.datagrid tbody').width();
        var h = $('.datagrid tbody').height();

        var iw = $('.datagrid_loading .indicator').width();
        var ih = $('.datagrid_loading .indicator').height();

        var itop = h/2 - ih/2 - 4;
        var ileft = w/2 - iw/2 - 4;

        $('.datagrid_loading').css('left', pos.left+'px').css('top', pos.top+'px');
        $('.datagrid_loading').css('width', w+'px').css('height', h+'px').css('line-height', h+'px');

        $('.datagrid_loading .indicator').css('top', itop+'px').css('left', ileft+'px');
    }

    this.hideLoadIndicator = function(){
        $('.datagrid_loading').hide();
    }
	
	this.escapeHtml = function(text) {
		return text
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#039;");
	}

    //====================================================================//


	return this;

}).call(icms.datagrid || {},jQuery);
