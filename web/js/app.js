/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

function goto_top() {
    $("html, body").animate({scrollTop:0}, 500, 'swing');
} // end function

function start() {
    $('#category-select').selectize({
        create: false,
        sortField: 'text',
        onChange: function(url){
            if ( window.location.pathname != url ) {
                window.location = url;
            } // end if
        }
    });
    $('#sorting-select').selectize({
        create: false,
        sortField: 'text',
        onChange: function(url){
            if ( window.location.search.indexOf(url) == -1 ) {
                window.location = url;
            } // end if
        }
    });
    if ( $('#categories-input').length ) {
        var options = JSON.parse( $('#categories-input').attr('data-json') );
        $('#categories-input').selectize({
            delimiter: ';',
            persist: true,
            options: options,
            create: false,
        });
    } // end if

    if ( $('.uploader').length ) {
        $('.uploader').each(function(){
            var selector = 'div#' + $(this).attr('id');
            var myDropzone = new Dropzone(selector, {
                url: "/upload/logo/",
                uploadMultiple: false,
                maxFiles: 1,
                method: 'POST',
                maxFilesize: 10,
                paramName: 'logo',
            });
        });
    } // end if
    
} // end function
