/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

function goto_top() {
    $("html, body").animate({ scrollTop: 0 }, 500, 'swing');
} // end function

function start() {
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    var $categoriesSelect = $('#category-select').selectize({
        create: false,
        sortField: 'text',
        onChange: function (url) {
            if (window.location.search.indexOf(url) == -1 && window.location.pathname != url) {
                window.location = url;
            } // end if
        }
    });
    // $categoriesSelect.closest('form').find('.selectize-input').on('click', function(){
    //     $categoriesSelect[0].selectize.clear();
    // });

    $('#sorting-select').selectize({
        create: false,
        sortField: 'text',
        onChange: function (url) {
            if (window.location.search.indexOf(url) == -1) {
                window.location = url;
            } // end if
        }
    });
    if ($('#categories-input').length) {
        var options = $('#categories-input').data('json');
        var assigned = $('#categories-input').data('assigned');
        $('#categories-input').selectize({
            delimiter: ';',
            persist: true,
            options: options,
            create: false,
            items: assigned,
            maxItems: 3
        });
    } // end if
    if ($('#types-input').length) {
        var options = $('#types-input').data('json');
        var assigned = $('#types-input').data('assigned');
        $('#types-input').selectize({
            delimiter: ';',
            persist: true,
            options: options,
            create: false,
            items: assigned,
            maxItems: 5
        });
    } // end if
    if ($('#labels-input').length) {
        var options = $('#labels-input').data('json');
        var assigned = $('#labels-input').data('assigned');
        $('#labels-input').selectize({
            delimiter: ';',
            persist: true,
            options: options,
            create: false,
            items: assigned,
            maxItems: 10
        });
    } // end if

    if ($('.uploader-area').length) {
        $('.uploader-area').each(function () {
            var $this = $(this);
            var selector = 'div#' + $this.attr('id');
            var dropZone = new Dropzone(selector, {
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
                init: function () {
                    // getQueuedFiles
                    var prevFile;
                    this.on('addedfile', function () {
                        this.files = this.files.slice(-1);
                        if (typeof prevFile !== "undefined") {
                            this.removeFile(prevFile);
                        } // end if
                        $this.find('.dz-preview').not(':last').remove();
                        var $name = $this.find('.dz-preview .dz-filename span');
                        if ($name.text().length > 20) {
                            $name.text('...' + $name.text().substr(-20));
                        } // end if
                        $this.closest('.col-uploader').addClass('has-img');
                    });
                    this.on('success', function (file, response) {
                        prevFile = file;
                        if ('ok' == response.result) {
                            $this.closest('form').find('#imgPath').val(response.images.img);
                            $this.closest('form').find('#tnPath').val(response.images.tn);
                        } // end if
                    });
                    this.on('dragenter', function () {
                        $this.find('.uploader').addClass('hovering');
                    }).on('drop', function (event) {
                        var myDropzone = this;
                        var imageUrl = event.dataTransfer.getData('URL');
                        var fileName = imageUrl.split('/').pop();
                        if (imageUrl.length > 0) {
                            imageUrl = wwwroot() + '/image-relay/?img=' + encodeURIComponent(imageUrl);
                        } // end if
                        if (fileName.indexOf('?') !== -1) {
                            fileName = fileName.split('?').shift();
                        } // end if

                        // set the effectAllowed for the drag item
                        // event.dataTransfer.effectAllowed = 'copy';
                        Dropzone.getDataUri(imageUrl, function (dataUri) {
                            var blob = Dropzone.dataURItoBlob(dataUri);
                            blob.name = fileName;
                            myDropzone.addFile(blob);
                        });
                        $this.find('.uploader').removeClass('hovering');
                    }).on('dragend', function () {
                        $this.find('.uploader').removeClass('hovering');
                    }).on('dragleave', function () {
                        $this.find('.uploader').removeClass('hovering');
                    });
                }
            });

            var $img = $this.closest('form').find('input[type="hidden"][name="new[img]"]');
            var $tn = $this.closest('form').find('input[type="hidden"][name="tn"]');
            if ($img.length && $img.val().length) {
                var fileName = $img.val().split('/').slice(-1);
                fileName = fileName[Object.keys(fileName)[0]];
                var mockFile = { name: fileName, size: 12345 };
                dropZone.emit("addedfile", mockFile);
                dropZone.emit("thumbnail", mockFile, $img.val());
            } // end if
            $this.find('.uploader').click(function () {
                $this.trigger('click');
            });
        });

    } // end if

    var $alert = $('.new-thing .similar-thing');
    var typingTimer = null;
    $('.new-thing #name').on('keyup', function () {
        clearTimeout(typingTimer);
        $alert.hide();
        var $this = $(this);
        if ($this.val().length > 2) {
            var lastNameVal = $this.val();
            typingTimer = setTimeout(function () {
                if ($this.val() == lastNameVal) {
                    $.ajax({
                        cache: true,
                        url: '/thing/similar/',
                        data: { name: lastNameVal },
                        success: function (data) {
                            if (data.length) {
                                $alert.find('.current-value').text('*' + lastNameVal + '*');
                                var lst = [];
                                $.each(data, function (key, val) {
                                    var o = '';
                                    o += '<a target="_blank" rel="noopener" href="' + val.url + '">';
                                    o += val.name;
                                    if (val.tn) {
                                        o += '<img src="' + val.tn + '" alt="' + val.name + '">';
                                    } // end if
                                    o += '</a>';
                                    lst.push(o);
                                }); // end foreach
                                $alert.find('.lst').html(lst.join(', '));
                                $alert.show();
                            } // end if
                        }, // end func
                    }); // end ajax
                } // end if
            }, 2000);
        } // end if
    });

    $('.new-thing form').on('submit', function (e) {
        var $form = $(this);
        e.preventDefault();
        $form.addClass('working');
        $form.find('.alert-danger, .alert-warning').remove();
        $.ajax({
            url: $form.attr('action'),
            method: $form.attr('method'),
            data: $form.serialize(),
            success: function (data) {
                var o = '';
                if (data.errors.length) {
                    $.each(data.errors, function (key, err) {
                        o += '<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + err + '</div>';
                    }); // end foreach
                    $form.prepend(o);
                    $("html, body").animate({ scrollTop: 0 }, 500, 'swing');
                } else {
                    window.location = data.url;
                } // end if
            }, // end func
            error: function (data) {
                var err = 'Unknown server error. Please try again later.';
                if (data && data.errors) {
                    err = data.errors.join(', ');
                } // end if
                $form.prepend('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + err + '</div>');
            }, // end func
            complete: function () {
                $form.removeClass('working');
            }
        }); // end ajax
    });

    $('input.thing-autocomplete').selectize({
        valueField: 'url',
        labelField: 'name',
        searchField: 'name',
        create: false,
        render: {
            option: function (item, escape) {
                var o = '';
                o += '<div class="fulltext-result ' + (item.cls ? item.cls : '') + '">';
                if (item.tn) {
                    o += '<img class="blend" src="' + escape(item.img) + '" alt="' + escape(item.name) + '">';
                } // end if

                o += escape(item.name);
                if (item.fa) {
                    o += '&nbsp;<i class="fa fa-' + item.fa + '"></i>';
                } // end if
                if (item.summary) {
                    o += '<br /><small>' + escape(item.summary) + '</small>';
                } // end if
                //o += '';
                o += '</div>';
                return o;
            }
        },
        load: function (query, callback) {
            if (!query.length) return callback();
            $.ajax({
                url: '/things/autocomplete/?q=' + encodeURIComponent(query),
                type: 'GET',
                error: function () {
                    callback();
                },
                success: function (res) {
                    callback(res);
                }
            });
        }
    }).change(function () {
        window.location = $(this).val();
    });
    setTimeout(function(){
        $('#index').find('.selectize-input input[type="text"]').focus()
    }, 100);

    $('[data-toggle="tooltip"]').tooltip();

    $('.page.thing .tap-to-edit').on('click', function (e) {
        e.preventDefault();
        $(this).addClass('hidden');
        var $input = $(this).closest('.crowd-col').find('select, .input');
        $input.removeClass('hidden');
        // var event = document.createEvent('MouseEvents');
        // event.initMouseEvent('mousedown', true, true, window);
        // $input[0].dispatchEvent(event);
        return false;
    });
    $('.page.thing .live-update').on('change', function (e) {
        e.preventDefault();
        var $this = $(this);
        var $tag = $this.closest('.crowd-col').prev().find('.saved');
        var val = $this.val();
        if (val.length > 0) {
            var id = $this.data('id');
            var slug = $this.data('slug');
            var data = {};
            data[slug] = val;
            $.ajax({
                method: 'PATCH',
                url: '/thing/patch/' + id + '/',
                data: data,
                complete: function (res) {
                    $tag.stop().fadeIn('slow').fadeOut(2000);
                }, // end func
            }); // end ajax
        } // end if
        return false;
    });

    if ($('input[name="new[homepage]"]').length) {
        $('input[name="new[homepage]"]').on('keydown', function (e) {
            if (9 == e.which) {
                e.preventDefault();
                $('#new-thing-uploader .uploader').trigger('click');
                return false;
            } // end if
        })
    } // end if

    window.addEventListener("dragover", function (e) {
        e = e || event;
        e.preventDefault();
    }, false);
    window.addEventListener("drop", function (e) {
        e = e || event;
        e.preventDefault();
    }, false);
} // end function

function toggle_description(el) {
    $('.thing-description').toggleClass('full');
    if ($(el).find('i.fa').hasClass('fa-angle-double-down')) {
        $(el).find('i.fa').addClass('fa-angle-double-up').removeClass('fa-angle-double-down');
    } else {
        $(el).find('i.fa').addClass('fa-angle-double-down').removeClass('fa-angle-double-up');
    } // end if-else
} // end function

function reject_thing(id, el) {
    $(el).addClass('disabled');
    $.ajax({
        method: 'PATCH',
        url: '/thing/reject/' + id + '/',
        success: function () {
            $(el).closest('.card').addClass('pointer-events-none').animate({ opacity: 0.00 }, 1000);
        }, // end func
    }); // end ajax
} // end function

function approve_thing(id, el) {
    $(el).addClass('disabled');
    $.ajax({
        method: 'PATCH',
        url: '/thing/approve/' + id + '/',
        success: function () {
            $(el).closest('.card').addClass('pointer-events-none').animate({ opacity: 0.00 }, 1000);
        }, // end func
    }); // end ajax
} // end function

Dropzone.getDataUri = function (url, callback) {
    var image = new Image();

    image.onload = function () {
        var canvas = document.createElement('canvas');
        canvas.width = this.naturalWidth; // or 'width' if you want a special/scaled size
        canvas.height = this.naturalHeight; // or 'height' if you want a special/scaled size

        canvas.getContext('2d').drawImage(this, 0, 0);

        // Get raw image data
        // callback(canvas.toDataURL('image/png').replace(/^data:image\/(png|jpg);base64,/, ''));

        // ... or get as Data URI
        callback(canvas.toDataURL('image/png'));
    };

    image.setAttribute('crossOrigin', 'anonymous');
    image.src = url;
} // end function

function wwwroot() {
    return window.location.protocol + '//' + window.location.host;
} // end function

function go_back(url) {
    if (history.length > 2) {
        window.history.back();
    } else if (url) {
        window.location = url;
    } else {
        window.history.replaceState(null, null, '/');
    } // end if-else
    return false;
} // end function
