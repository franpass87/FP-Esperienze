/**
 * FP Esperienze Booking Widget JavaScript
 *
 * @package FP\Esperienze
 */

(function($) {
    'use strict';

    class BookingWidget {
        constructor() {
            this.widget = $('#fp-booking-widget');
            this.productId = this.widget.data('product-id');
            this.selectedDate = null;
            this.selectedSlot = null;
            this.adults = 1;
            this.children = 0;
            this.currentStep = 'date';
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.updateStepVisibility();
        }
        
        bindEvents() {
            // Date picker change
            $('#fp-date-picker').on('change', (e) => {
                this.selectedDate = e.target.value;
                if (this.selectedDate) {
                    this.loadAvailability();
                }
            });
            
            // Slot selection
            $(document).on('click', '.fp-slot-item:not(.fp-slot-disabled)', (e) => {
                this.selectSlot($(e.currentTarget));
            });
            
            // Quantity controls
            $('.fp-qty-btn').on('click', (e) => {
                this.handleQuantityChange($(e.currentTarget));
            });
            
            $('.fp-qty-input').on('change', (e) => {
                this.updateQuantity($(e.currentTarget));
            });
            
            // Add to cart
            $('#fp-add-to-cart').on('click', () => {
                this.addToCart();
            });
        }
        
        loadAvailability() {
            this.showLoading();
            
            const url = `${fpEsperienze.restUrl}availability?product_id=${this.productId}&date=${this.selectedDate}`;
            
            $.get(url)
                .done((response) => {
                    this.renderSlots(response.slots);
                    this.currentStep = 'slots';
                    this.updateStepVisibility();
                })
                .fail((xhr) => {
                    this.showError(xhr.responseJSON?.message || fpEsperienze.strings.loading);
                })
                .always(() => {
                    this.hideLoading();
                });
        }
        
        renderSlots(slots) {
            const container = $('#fp-slots-container');
            container.empty();
            
            slots.forEach(slot => {
                const slotElement = this.createSlotElement(slot);
                container.append(slotElement);
            });
        }
        
        createSlotElement(slot) {
            const isDisabled = !slot.available;
            const isSoldOut = slot.capacity_left <= 0;
            const isLastSpots = slot.capacity_left <= 5 && slot.capacity_left > 0;
            
            let statusText = '';
            let statusClass = '';
            
            if (isSoldOut) {
                statusText = fpEsperienze.strings.soldOut;
                statusClass = 'fp-slot-sold-out';
            } else if (isLastSpots) {
                statusText = fpEsperienze.strings.lastSpots.replace('%d', slot.capacity_left);
                statusClass = 'fp-slot-warning';
            } else if (slot.cutoff_passed) {
                statusText = 'Cutoff passed';
                statusClass = 'fp-slot-sold-out';
            } else {
                statusText = `${slot.capacity_left} spots`;
                statusClass = 'fp-slot-capacity';
            }
            
            return $(`
                <div class="fp-slot-item ${isDisabled ? 'fp-slot-disabled' : ''}" 
                     data-time="${slot.time}" 
                     data-capacity-left="${slot.capacity_left}">
                    <div class="fp-slot-time">${slot.time}</div>
                    <div class="fp-slot-info">
                        <span class="${statusClass}">${statusText}</span>
                    </div>
                </div>
            `);
        }
        
        selectSlot($slotElement) {
            // Remove previous selection
            $('.fp-slot-item').removeClass('fp-slot-selected');
            
            // Select new slot
            $slotElement.addClass('fp-slot-selected');
            this.selectedSlot = {
                time: $slotElement.data('time'),
                capacityLeft: $slotElement.data('capacity-left')
            };
            
            // Move to quantity step
            this.currentStep = 'quantity';
            this.updateStepVisibility();
            this.updateSummary();
        }
        
        handleQuantityChange($button) {
            const target = $button.data('target');
            const $input = $(`#${target}`);
            const currentValue = parseInt($input.val()) || 0;
            const isPlus = $button.hasClass('fp-qty-plus');
            const isMinus = $button.hasClass('fp-qty-minus');
            
            let newValue = currentValue;
            
            if (isPlus) {
                newValue = Math.min(currentValue + 1, 20);
            } else if (isMinus) {
                const minValue = target === 'fp-adults' ? 1 : 0;
                newValue = Math.max(currentValue - 1, minValue);
            }
            
            $input.val(newValue);
            this.updateQuantity($input);
        }
        
        updateQuantity($input) {
            const inputId = $input.attr('id');
            const value = parseInt($input.val()) || 0;
            
            if (inputId === 'fp-adults') {
                this.adults = value;
            } else if (inputId === 'fp-children') {
                this.children = value;
            }
            
            // Validate total capacity
            const totalQuantity = this.adults + this.children;
            if (totalQuantity > this.selectedSlot.capacityLeft) {
                this.showError(`Only ${this.selectedSlot.capacityLeft} spots available for this time slot`);
                return;
            }
            
            this.updateSummary();
            
            // Show cart step if quantities are valid
            if (this.adults > 0) {
                this.currentStep = 'cart';
                this.updateStepVisibility();
            }
        }
        
        updateSummary() {
            if (!this.selectedDate || !this.selectedSlot) return;
            
            const $details = $('#fp-booking-details');
            const $total = $('#fp-total-amount');
            
            // Get prices (these would normally come from the product)
            const adultPrice = parseFloat($('.woocommerce-Price-amount').first().text().replace(/[^0-9.,]/g, '').replace(',', '.')) || 50;
            const childPrice = adultPrice * 0.7; // 30% discount for children
            
            const adultTotal = this.adults * adultPrice;
            const childTotal = this.children * childPrice;
            const total = adultTotal + childTotal;
            
            const detailsHtml = `
                <div class="fp-booking-detail-item">
                    <span>Date:</span>
                    <span>${this.formatDate(this.selectedDate)}</span>
                </div>
                <div class="fp-booking-detail-item">
                    <span>Time:</span>
                    <span>${this.selectedSlot.time}</span>
                </div>
                ${this.adults > 0 ? `
                <div class="fp-booking-detail-item">
                    <span>Adults (${this.adults}):</span>
                    <span>€${adultTotal.toFixed(2)}</span>
                </div>
                ` : ''}
                ${this.children > 0 ? `
                <div class="fp-booking-detail-item">
                    <span>Children (${this.children}):</span>
                    <span>€${childTotal.toFixed(2)}</span>
                </div>
                ` : ''}
            `;
            
            $details.html(detailsHtml);
            $total.text(`€${total.toFixed(2)}`);
        }
        
        updateStepVisibility() {
            $('.fp-booking-step').hide();
            
            switch (this.currentStep) {
                case 'date':
                    $('.fp-step-date').show();
                    break;
                case 'slots':
                    $('.fp-step-date, .fp-step-slots').show();
                    break;
                case 'quantity':
                    $('.fp-step-date, .fp-step-slots, .fp-step-quantity').show();
                    break;
                case 'cart':
                    $('.fp-step-date, .fp-step-slots, .fp-step-quantity, .fp-step-cart').show();
                    break;
            }
        }
        
        addToCart() {
            if (!this.selectedDate || !this.selectedSlot || this.adults < 1) {
                this.showError('Please complete all booking steps');
                return;
            }
            
            const $button = $('#fp-add-to-cart');
            $button.prop('disabled', true).text('Adding...');
            
            $.post(fpEsperienze.ajaxUrl, {
                action: 'fp_add_experience_to_cart',
                nonce: fpEsperienze.nonce,
                product_id: this.productId,
                date: this.selectedDate,
                time: this.selectedSlot.time,
                adults: this.adults,
                children: this.children
            })
            .done((response) => {
                if (response.success) {
                    this.showSuccess(response.data.message);
                    // Optionally redirect to cart
                    setTimeout(() => {
                        window.location.href = response.data.cart_url;
                    }, 1500);
                } else {
                    this.showError(response.data);
                }
            })
            .fail(() => {
                this.showError('Failed to add to cart. Please try again.');
            })
            .always(() => {
                $button.prop('disabled', false).text(fpEsperienze.strings.addToCart);
            });
        }
        
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        showLoading() {
            $('.fp-loading').show();
        }
        
        hideLoading() {
            $('.fp-loading').hide();
        }
        
        showError(message) {
            this.hideMessages();
            this.widget.prepend(`<div class="fp-error-message">${message}</div>`);
            setTimeout(() => this.hideMessages(), 5000);
        }
        
        showSuccess(message) {
            this.hideMessages();
            this.widget.prepend(`<div class="fp-success-message">${message}</div>`);
        }
        
        hideMessages() {
            $('.fp-error-message, .fp-success-message').remove();
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        if ($('#fp-booking-widget').length) {
            new BookingWidget();
        }
    });

})(jQuery);