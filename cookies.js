( function( $ ) {
 
    $.fn.cookies = function( options ) {
        
        var clickEvent = 'click';
        var drag = false;

        if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
            clickEvent = 'touchend';
        }

        $( document ).on("touchmove", function(){
            drag = true;
        });
    
        $( document ).on("touchstart", function(){
            drag = false;
        });

        var defaults = {
            parent: 'body',
            panelClass: 'cookies-panel',
            parentDir: 'extensions/cookies-banner/',
            settingsButton: ''
        };
 
        var settings = $.extend( {}, defaults, options );
        var cookiesPanel;
        var confirmed;

        this.initialize = function(){

            //showSettings();
            return this;
        };

        this.showPanel = function( show ){ showPanel( show ); };
        var showPanel = function( show ){
            if( show !== undefined ){ settings.show = show; }
            
            var data = {
                type: 'render-panel',
                settings: settings
            }

            action( data, function( response ){
                if( response.success ){
                    cookiesPanel = $(settings.parent).append( response.html );
                    
                    cookiesPanel = $( settings.parent ).find('div.'+ settings.panelClass);
                    
                    setTimeout( function(){ cookiesPanel.addClass('active'); }, 10);

                    initializeButtons();
                }
            });
        };

        this.hidePanel = function(){ hidePanel(); };
        var hidePanel = function( now, update ){
            if( now === undefined ) now = false;
            if( update === undefined ) update = false;
            
            $( settings.parent ).find('div.'+ settings.panelClass).each( function(index, value){
                var panel = $(this);
                if( now ){
                    panel.remove();
                    return;
                }

                panel.removeClass('active');
                setTimeout( function(){ panel.remove(); }, 300);
            });

            if( !update ) return;
            
            var ad_storage = ( confirmed.indexOf('marketing') > 0 ) ? 'granted' : 'denied';
            var analytics_storage = ( confirmed.indexOf('statistics') > 0 ) ? 'granted' : 'denied';

            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'update', {
                'ad_storage': ad_storage,
                'analytics_storage': analytics_storage,
                'functionality_storage': 'denied',
                'personalization_storage': 'denied',
                'security_storage': 'denied'
            });
            dataLayer.push({'event': 'cookies_update'});

            //cookies_update
            //cookiesPanel.remove();
        };

        var initializeButtons = function(){
            cookiesPanel.on( clickEvent, 'button.allow-all', function(event){
                event.preventDefault();
                if( drag ) return;

                var data = {
                    type: 'allow-all'
                }

                action( data, function( response ){
                    if( response.success ){
                        hidePanel( false, true );
                    }
                });
            });

            cookiesPanel.on( clickEvent, 'button.allow-selected', function(event){
                event.preventDefault();
                if( drag ) return;

                var categories = cookiesPanel.find('span.checkbox');
                var values = [];
                $.each( categories, function( index, category ){
                    if( !$(category).hasClass('selected') ){ return; }

                    values.push( $(category).attr('data-value') );
                });

                var data = {
                    type: 'allow-selected',
                    values: values
                };

                action( data, function( response ){
                    if( response.success ){
                        hidePanel( false, true );
                    }
                });
            });

            cookiesPanel.on( clickEvent, 'button.deny-all', function(event){
                event.preventDefault();
                if( drag ) return;

                var data = {
                    type: 'deny-all'
                }

                action( data, function( response ){
                    if( response.success ){
                        hidePanel( false, true );
                    }
                });
            });

            cookiesPanel.on( clickEvent, 'button.more', function(event){
                event.preventDefault();
                if( drag ) return;

                hidePanel(true);
                showPanel('more');
            });

            cookiesPanel.on( clickEvent, 'button.back', function(event){
                event.preventDefault();
                if( drag ) return;

                hidePanel(true);
                showPanel('main');
            });

            cookiesPanel.on( clickEvent, 'button.cancel,img.cancel', function(event){
                event.preventDefault();
                if( drag ) return;

                hidePanel();
            });

            cookiesPanel.on( clickEvent, 'label', function(event){
                event.preventDefault();
                if( drag ) return;

                var checkbox = $(this).find('span.checkbox');
                if( checkbox.hasClass('disabled') ){ return; }

                checkbox.toggleClass('selected')
            });
        };

        var action = function( data, callback ){
            var async = ( data.async === undefined ) ? true : data.async;
            data.cookiepanel = true;

            return $.ajax({
                url: './'+ settings.parentDir +'ajax/config.php',
                type: 'post',
                async: async,
                data: data,
                success: function( response ){
                    try{
                        response = JSON.parse( response );

                        checkConfirmed( response.confirmed );
                        confirmed = response.confirmed;

                        if( callback ){
                            callback( response )
                        }
                    } catch(e){
                        console.log( e );
                        console.log( response );
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log(textStatus, errorThrown);
                }
            });
        }
        
        if( settings.settingsButton.length > 0 ){
            $(document).on( clickEvent, settings.settingsButton, function(event){
                event.preventDefault();
                if( drag ) return;

                hidePanel(true);
                showPanel('only-more');
            });
        }

        this.isConfirmed = function(category){ isConfirmed(category); };
        var isConfirmed = function(category){
            if( category === undefined ) return false;
            
            var data = {
                async: false,
                type: 'check-confirm',
                category: category
            }
            
            var confirm = action( data );
            try{
                response = JSON.parse( confirm.responseText );

                return response.confirm;
            } catch(e){
                console.log( e );
                console.log( response );
            }
        };

        checkConfirmed = function( confirmed ){
            $('.cookies-check-confirm').each( function(index, value){
                var category = $(value).attr('data-category');

                if( confirmed.indexOf(category) < 0 ) $(value).remove();
            });
        }

        this.adminPage = function(){
            $(document).on( clickEvent, 'div.section', function(event){
                event.preventDefault();
                if( drag ) return;

                $(this).next('div.details').toggleClass('active');
            });

            $(document).on( clickEvent, 'div.item > div.name', function(event){
                event.preventDefault();
                if( drag ) return;

                $(this).next('div.more').toggleClass('active');
            });
        };
        
        return this.initialize();
    };
 
}( jQuery ));


    
