/**
 * Frontend Tracking for GA4 and Meta Pixel
 *
 * @package FP\Esperienze
 */

(function($) {
    'use strict';

    window.FPTracking = {
        
        /**
         * Initialize tracking
         */
        init: function() {
            this.settings = window.fpTrackingSettings || {};
            
            // Initialize consent mode
            this.initConsentMode();
            
            // Process any pending tracking events
            this.processPendingEvents();
            
            // Bind to cart events
            this.bindEvents();
        },
        
        /**
         * Initialize consent mode functionality
         */
        initConsentMode: function() {
            // Create public API for consent checking
            window.fpExpGetConsent = this.getConsentStatus.bind(this);
        },
        
        /**
         * Get consent status for marketing
         */
        getConsentStatus: function() {
            if (!this.settings.consent_mode_enabled) {
                // Consent mode disabled, always allow tracking
                return true;
            }
            
            // Try JavaScript function first (if configured)
            if (this.settings.consent_js_function) {
                try {
                    const funcPath = this.settings.consent_js_function.split('.');
                    let func = window;
                    for (const part of funcPath) {
                        func = func[part];
                        if (!func) break;
                    }
                    if (typeof func === 'function') {
                        const result = func();
                        return Boolean(result);
                    }
                } catch (e) {
                    console.warn('FP Esperienze: Error calling consent function', e);
                }
            }
            
            // Fall back to cookie checking
            const cookieName = this.settings.consent_cookie_name || 'marketing_consent';
            const cookieValue = this.getCookie(cookieName);
            
            if (cookieValue === null) {
                // No consent cookie found, default to false
                return false;
            }
            
            // Check for various truthy values
            const normalizedValue = cookieValue.toLowerCase();
            return normalizedValue === 'true' || normalizedValue === '1' || normalizedValue === 'granted';
        },
        
        /**
         * Get cookie value by name
         */
        getCookie: function(name) {
            const value = '; ' + document.cookie;
            const parts = value.split('; ' + name + '=');
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        },
        
        /**
         * Process pending tracking events from server
         */
        processPendingEvents: function() {
            if (typeof window.fpTrackingData !== 'undefined') {
                const data = window.fpTrackingData;
                
                if (data.add_to_cart) {
                    this.trackGA4Event('add_to_cart', {
                        currency: this.settings.currency,
                        value: data.add_to_cart.price * data.add_to_cart.quantity,
                        items: [{
                            item_id: data.add_to_cart.product_id,
                            item_name: data.add_to_cart.product_name,
                            item_category: 'Experience',
                            price: data.add_to_cart.price,
                            quantity: data.add_to_cart.quantity,
                            slot_start: data.add_to_cart.slot_start,
                            meeting_point_id: data.add_to_cart.meeting_point_id,
                            lang: data.add_to_cart.lang
                        }]
                    });
                    
                    this.trackMetaEvent('AddToCart', {
                        value: data.add_to_cart.price * data.add_to_cart.quantity,
                        currency: this.settings.currency,
                        content_ids: [data.add_to_cart.product_id],
                        content_type: 'product'
                    });
                }
                
                if (data.begin_checkout) {
                    this.trackGA4Event('begin_checkout', {
                        currency: data.begin_checkout.currency,
                        value: data.begin_checkout.value,
                        items: data.begin_checkout.items
                    });
                    
                    this.trackMetaEvent('InitiateCheckout', {
                        value: data.begin_checkout.value,
                        currency: data.begin_checkout.currency,
                        content_ids: data.begin_checkout.items.map(item => item.item_id),
                        content_type: 'product'
                    });
                }
                
                if (data.add_payment_info) {
                    this.trackGA4Event('add_payment_info', {
                        currency: data.add_payment_info.currency,
                        value: data.add_payment_info.value,
                        items: data.add_payment_info.items
                    });
                }
                
                if (data.purchase) {
                    this.trackGA4Event('purchase', {
                        transaction_id: data.purchase.transaction_id,
                        currency: data.purchase.currency,
                        value: data.purchase.value,
                        items: data.purchase.items
                    });
                    
                    this.trackMetaEvent('Purchase', {
                        value: data.purchase.value,
                        currency: data.purchase.currency,
                        content_ids: data.purchase.items.map(item => item.item_id),
                        content_type: 'product'
                    }, data.purchase.event_id);
                }
            }
        },
        
        /**
         * Bind tracking events
         */
        bindEvents: function() {
            // Listen for custom tracking events
            $(document).on('fp_track_view_item', (e, data) => {
                this.trackViewItem(data);
            });
            
            $(document).on('fp_track_select_item', (e, data) => {
                this.trackSelectItem(data);
            });
        },
        
        /**
         * Track view item event
         */
        trackViewItem: function(data) {
            const eventData = {
                currency: this.settings.currency,
                value: data.price || 0,
                items: [{
                    item_id: data.product_id,
                    item_name: data.product_name,
                    item_category: 'Experience',
                    price: data.price || 0,
                    quantity: 1,
                    slot_start: data.slot_start || null,
                    meeting_point_id: data.meeting_point_id || null,
                    lang: data.lang || null
                }]
            };
            
            this.trackGA4Event('view_item', eventData);
        },
        
        /**
         * Track select item event (slot selection)
         */
        trackSelectItem: function(data) {
            const eventData = {
                item_list_name: 'Available Time Slots',
                items: [{
                    item_id: data.product_id,
                    item_name: data.product_name,
                    item_category: 'Experience',
                    price: data.price,
                    quantity: 1,
                    slot_start: data.slot_start,
                    meeting_point_id: data.meeting_point_id,
                    lang: data.lang
                }]
            };
            
            this.trackGA4Event('select_item', eventData);
        },
        
        /**
         * Track GA4 event
         */
        trackGA4Event: function(eventName, eventData) {
            if (!this.settings.ga4_enabled) {
                return;
            }
            
            // Check consent if consent mode is enabled
            if (this.settings.consent_mode_enabled && !this.getConsentStatus()) {
                console.log('FP Esperienze: GA4 event blocked due to consent:', eventName);
                return;
            }
            
            if (typeof window.dataLayer !== 'undefined') {
                window.dataLayer.push({
                    event: eventName,
                    ecommerce: eventData
                });
                
                console.log('GA4 Event:', eventName, eventData);
            }
        },
        
        /**
         * Track Meta Pixel event
         */
        trackMetaEvent: function(eventName, eventData, eventId = null) {
            if (!this.settings.meta_enabled) {
                return;
            }
            
            // Check consent if consent mode is enabled
            if (this.settings.consent_mode_enabled && !this.getConsentStatus()) {
                console.log('FP Esperienze: Meta Pixel event blocked due to consent:', eventName);
                return;
            }
            
            if (typeof window.fbq !== 'undefined') {
                const trackData = eventData;
                
                if (eventId) {
                    trackData.event_id = eventId;
                }
                
                window.fbq('track', eventName, trackData);
                
                console.log('Meta Pixel Event:', eventName, trackData);
            }
        },
        
        /**
         * Generate consistent event ID for deduplication
         */
        generateEventId: function(eventType, additionalData = '') {
            const timestamp = Date.now();
            const random = Math.random().toString(36).substr(2, 9);
            return eventType + '_' + timestamp + '_' + random + additionalData;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        window.FPTracking.init();
    });

})(jQuery);