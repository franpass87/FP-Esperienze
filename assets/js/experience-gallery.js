(function() {
    'use strict';

    function ExperienceGallery(element) {
        this.container = element;
        this.stage = element.querySelector('.fp-experience-gallery__stage');
        this.slides = Array.prototype.slice.call(element.querySelectorAll('.fp-experience-gallery__slide'));
        this.thumbs = Array.prototype.slice.call(element.querySelectorAll('.fp-experience-gallery__thumb'));
        this.prev = element.querySelector('.fp-experience-gallery__control--prev');
        this.next = element.querySelector('.fp-experience-gallery__control--next');
        this.index = 0;
        this.autoPlayInterval = 3000;
        this.autoPlayTimer = null;
        this.isPaused = false;

        if (!this.slides.length) {
            return;
        }

        if (!this.container.hasAttribute('tabindex')) {
            this.container.setAttribute('tabindex', '0');
        }

        this.bindEvents();
        this.goTo(0, false);
        this.initAutoplay();
    }

    ExperienceGallery.prototype.bindEvents = function() {
        var self = this;

        if (this.prev) {
            this.prev.addEventListener('click', function(event) {
                event.preventDefault();
                self.goTo(self.index - 1, false);
            });
        }

        if (this.next) {
            this.next.addEventListener('click', function(event) {
                event.preventDefault();
                self.goTo(self.index + 1, false);
            });
        }

        this.thumbs.forEach(function(thumb, position) {
            thumb.addEventListener('click', function(event) {
                event.preventDefault();
                self.goTo(position, false);
            });

            thumb.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    self.goTo(position, false);
                }
            });
        });

        this.container.addEventListener('keydown', function(event) {
            if (event.key === 'ArrowLeft') {
                self.goTo(self.index - 1, true);
            }

            if (event.key === 'ArrowRight') {
                self.goTo(self.index + 1, true);
            }
        });
    };

    ExperienceGallery.prototype.goTo = function(targetIndex, focusThumb) {
        var total = this.slides.length;
        if (!total) {
            return;
        }

        if (typeof focusThumb === 'undefined') {
            focusThumb = true;
        }

        if (targetIndex < 0) {
            targetIndex = total - 1;
        }

        if (targetIndex >= total) {
            targetIndex = 0;
        }

        this.index = targetIndex;

        for (var i = 0; i < total; i++) {
            var isActive = i === targetIndex;
            var slide = this.slides[i];
            var thumb = this.thumbs[i];

            slide.classList.toggle('is-active', isActive);
            slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');

            if (thumb) {
                thumb.classList.toggle('is-active', isActive);
                thumb.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                thumb.setAttribute('tabindex', isActive ? '0' : '-1');
            }
        }

        if (focusThumb && this.thumbs[this.index]) {
            try {
                this.thumbs[this.index].focus({ preventScroll: true });
            } catch (error) {
                this.thumbs[this.index].focus();
            }
        }

        if (!this.isPaused) {
            this.scheduleNextSlide();
        }
    };

    ExperienceGallery.prototype.initAutoplay = function() {
        if (this.slides.length <= 1) {
            return;
        }

        var intervalAttribute = this.container.getAttribute('data-autoplay-interval');
        if (intervalAttribute) {
            var parsedInterval = parseInt(intervalAttribute, 10);
            if (!isNaN(parsedInterval) && parsedInterval > 0) {
                this.autoPlayInterval = parsedInterval;
            }
        }

        var self = this;

        this.container.addEventListener('mouseenter', function() {
            self.setPaused(true);
        });

        this.container.addEventListener('mouseleave', function() {
            self.setPaused(false);
        });

        this.container.addEventListener('focusin', function() {
            self.setPaused(true);
        });

        this.container.addEventListener('focusout', function() {
            var resume = function() {
                if (!self.container.contains(document.activeElement)) {
                    self.setPaused(false);
                }
            };

            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(resume);
            } else {
                window.setTimeout(resume, 0);
            }
        });

        this.container.addEventListener('touchstart', function() {
            self.setPaused(true);
        });

        this.container.addEventListener('touchend', function() {
            self.setPaused(false);
        });

        this.scheduleNextSlide();
    };

    ExperienceGallery.prototype.setPaused = function(paused) {
        if (this.isPaused === paused) {
            return;
        }

        this.isPaused = paused;

        if (!paused) {
            this.scheduleNextSlide();
        } else {
            this.clearScheduledSlide();
        }
    };

    ExperienceGallery.prototype.scheduleNextSlide = function() {
        if (this.slides.length <= 1) {
            return;
        }

        this.clearScheduledSlide();

        var self = this;
        this.autoPlayTimer = window.setTimeout(function() {
            self.autoPlayTimer = null;

            if (self.isPaused) {
                self.scheduleNextSlide();
                return;
            }

            self.goTo(self.index + 1, false);
        }, this.autoPlayInterval);
    };

    ExperienceGallery.prototype.clearScheduledSlide = function() {
        if (this.autoPlayTimer) {
            window.clearTimeout(this.autoPlayTimer);
            this.autoPlayTimer = null;
        }
    };

    function initGalleries() {
        var galleries = document.querySelectorAll('.fp-experience-gallery');
        galleries.forEach(function(element) {
            new ExperienceGallery(element);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGalleries);
    } else {
        initGalleries();
    }
})();
