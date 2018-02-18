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

    if ( $('.uploader-area').length ) {
        $('.uploader-area').each(function(){
            var $this = $(this);
            var selector = 'div#' + $this.attr('id');
            var myDropzone = new Dropzone(selector, {
                url: "/upload/logo/",
                uploadMultiple: false,
                maxFiles: 1,
                method: 'POST',
                maxFilesize: 10,
                paramName: 'logo',
                thumbnailWidth: 100,
                thumbnailHeight: 100,
                thumbnailMethod: 'contain',
                clickable: true,
                acceptedFiles: '.jpg,.jpeg,.png,.bmp,.gif',
                // autoProcessQueue: false,
                init: function(){
                    // getQueuedFiles
                    var prevFile;
                    this.on('addedfile', function() {
                        this.files = this.files.slice(-1);
                        if (typeof prevFile !== "undefined") {
                            this.removeFile(prevFile);
                        } // end if
                        $this.find('.dz-preview').not(':last').remove();
                        // this.processQueue();
                    });
                    this.on('success', function(file, response) {
                        prevFile = file;
                        if ( 'ok' == response.result ) {
                            $this.closest('form').find('#imgPath').val(response.images.img);
                            $this.closest('form').find('#tnPath').val(response.images.tn);
                        } // end if
                    });
                    this.on('dragenter', function(){
                        $this.find('.uploader').addClass('hovering');
                    }).on('drop', function(){
                        $this.find('.uploader').removeClass('hovering');
                    }).on('dragend', function(){
                        $this.find('.uploader').removeClass('hovering');
                    }).on('dragleave', function(){
                        $this.find('.uploader').removeClass('hovering');
                    });
                }
            });
            $this.find('.uploader').click(function(){
                $this.trigger('click');
            });
        });
    } // end if

    var $alert = $('.new-thing .similar-thing');
    var typingTimer = null;
    $('.new-thing #name').on('keyup', function(){
        clearTimeout(typingTimer);
        $alert.hide();
        var $this = $(this);
        if ( $this.val().length > 2 ) {
            var lastNameVal = $this.val();
            typingTimer = setTimeout(function(){
                if ( $this.val() == lastNameVal ) {
                    $.ajax({
                        cache: true,
                        url: '/thing/similar',
                        data: { name: lastNameVal },
                        success: function(data) {
                            if ( data.length ) {
                                $alert.find('.current-value').text('*' + lastNameVal + '*');
                                var lst = [];
                                $.each(data, function(key, val) {
                                    var o = '';
                                    o += '<a target="_blank" href="'+val.url+'">';
                                    o += val.name;
                                    if ( val.tn ) {
                                        o += '<img src="'+val.tn+'" alt="'+val.name+'">';    
                                    } // end if
                                    o += '</a>';
                                    lst.push(o);
                                }); // end foreach
                                $alert.find('.lst').html( lst.join(', ') );
                                $alert.show();  
                            } // end if
                        }, // end func
                    }); // end ajax
                } // end if
            }, 2000);
        } // end if
    });

    $('.new-thing form').on('submit', function(e){
        var $form = $(this);
        e.preventDefault();
        $form.find('.alert-danger, .alert-warning').remove();
        $.ajax({
            url: $form.attr('action'),
            method: $form.attr('method'),
            data: $form.serialize(),
            success: function(data) {
                var o = '';
                if ( data.errors.length ) {
                    $.each(data.errors, function(key, err) {
                        o += '<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>'+err+'</div>';
                    }); // end foreach
                    $form.prepend(o);
                } else {
                    window.location = data.url;
                } // end if
            }, // end func
            error: function(data) {
                var err = 'Unknown server error. Please try again later.';
                if ( data && data.errors ) {
                    err = data.errors.join(', ');
                } // end if
                $form.prepend('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>'+err+'</div>');
            }, // end func
        }); // end ajax
    })
} // end function

function toggle_description(el) {
    $('.thing-description').toggleClass('full');
    if ( $(el).find('i.fa').hasClass('fa-angle-double-down') ) {
        $(el).find('i.fa').addClass('fa-angle-double-up').removeClass('fa-angle-double-down');
    } else {
        $(el).find('i.fa').addClass('fa-angle-double-down').removeClass('fa-angle-double-up');
    } // end if-else
}