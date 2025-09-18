/**
 * FP Esperienze Archive Block
 */

if (typeof jQuery === 'undefined') {
    console.error('FP Esperienze: jQuery is required for the archive block.');
    return;
}

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { PanelBody, ToggleControl, RangeControl, SelectControl } = wp.components;
    const { InspectorControls } = wp.editor;
    const { createElement, Fragment } = wp.element;
    const { __ } = wp.i18n;

    registerBlockType('fp-esperienze/archive', {
        title: __('Experience Archive', 'fp-esperienze'),
        description: __('Display a filterable archive of experiences', 'fp-esperienze'),
        icon: 'archive',
        category: 'widgets',
        keywords: [
            __('experience', 'fp-esperienze'),
            __('archive', 'fp-esperienze'),
            __('filter', 'fp-esperienze')
        ],

        attributes: {
            postsPerPage: {
                type: 'number',
                default: 12
            },
            columns: {
                type: 'number',
                default: 3
            },
            orderBy: {
                type: 'string',
                default: 'date'
            },
            order: {
                type: 'string', 
                default: 'DESC'
            },
            enableLanguageFilter: {
                type: 'boolean',
                default: false
            },
            enableMeetingPointFilter: {
                type: 'boolean',
                default: false
            },
            enableDurationFilter: {
                type: 'boolean',
                default: false
            },
            enableDateFilter: {
                type: 'boolean',
                default: false
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const {
                postsPerPage,
                columns,
                orderBy,
                order,
                enableLanguageFilter,
                enableMeetingPointFilter,
                enableDurationFilter,
                enableDateFilter
            } = attributes;

            const orderByOptions = [
                { label: __('Date', 'fp-esperienze'), value: 'date' },
                { label: __('Name', 'fp-esperienze'), value: 'name' },
                { label: __('Price', 'fp-esperienze'), value: 'price' },
                { label: __('Duration', 'fp-esperienze'), value: 'duration' }
            ];

            const orderOptions = [
                { label: __('Descending', 'fp-esperienze'), value: 'DESC' },
                { label: __('Ascending', 'fp-esperienze'), value: 'ASC' }
            ];

            // Build active filters list for display
            const activeFilters = [];
            if (enableLanguageFilter) activeFilters.push(__('Language', 'fp-esperienze'));
            if (enableMeetingPointFilter) activeFilters.push(__('Meeting Point', 'fp-esperienze'));
            if (enableDurationFilter) activeFilters.push(__('Duration', 'fp-esperienze'));
            if (enableDateFilter) activeFilters.push(__('Date', 'fp-esperienze'));

            return createElement(Fragment, {},
                // Inspector Controls (Sidebar)
                createElement(InspectorControls, {},
                    createElement(PanelBody, {
                        title: __('Display Settings', 'fp-esperienze'),
                        initialOpen: true
                    },
                        createElement(RangeControl, {
                            label: __('Posts per page', 'fp-esperienze'),
                            value: postsPerPage,
                            onChange: (value) => setAttributes({ postsPerPage: value }),
                            min: 1,
                            max: 50
                        }),
                        createElement(RangeControl, {
                            label: __('Columns', 'fp-esperienze'),
                            value: columns,
                            onChange: (value) => setAttributes({ columns: value }),
                            min: 1,
                            max: 4
                        }),
                        createElement(SelectControl, {
                            label: __('Order by', 'fp-esperienze'),
                            value: orderBy,
                            options: orderByOptions,
                            onChange: (value) => setAttributes({ orderBy: value })
                        }),
                        createElement(SelectControl, {
                            label: __('Order', 'fp-esperienze'),
                            value: order,
                            options: orderOptions,
                            onChange: (value) => setAttributes({ order: value })
                        })
                    ),

                    createElement(PanelBody, {
                        title: __('Filters', 'fp-esperienze'),
                        initialOpen: false
                    },
                        createElement(ToggleControl, {
                            label: __('Enable Language Filter', 'fp-esperienze'),
                            checked: enableLanguageFilter,
                            onChange: (value) => setAttributes({ enableLanguageFilter: value })
                        }),
                        createElement(ToggleControl, {
                            label: __('Enable Meeting Point Filter', 'fp-esperienze'),
                            checked: enableMeetingPointFilter,
                            onChange: (value) => setAttributes({ enableMeetingPointFilter: value })
                        }),
                        createElement(ToggleControl, {
                            label: __('Enable Duration Filter', 'fp-esperienze'),
                            checked: enableDurationFilter,
                            onChange: (value) => setAttributes({ enableDurationFilter: value })
                        }),
                        createElement(ToggleControl, {
                            label: __('Enable Date Availability Filter', 'fp-esperienze'),
                            checked: enableDateFilter,
                            onChange: (value) => setAttributes({ enableDateFilter: value })
                        })
                    )
                ),

                // Block Preview (Editor Display)
                createElement('div', {
                    className: 'fp-experience-archive-block-preview',
                    style: {
                        border: '2px dashed #ddd',
                        padding: '20px',
                        textAlign: 'center',
                        backgroundColor: '#f9f9f9',
                        borderRadius: '8px'
                    }
                },
                    createElement('div', {
                        style: {
                            fontSize: '18px',
                            fontWeight: 'bold',
                            marginBottom: '10px',
                            color: '#333'
                        }
                    }, __('🎯 Experience Archive', 'fp-esperienze')),

                    createElement('div', {
                        style: {
                            fontSize: '14px',
                            color: '#666',
                            marginBottom: '15px'
                        }
                    }, 
                        __('Displaying', 'fp-esperienze') + ' ' + postsPerPage + ' ' + 
                        __('experiences in', 'fp-esperienze') + ' ' + columns + ' ' + 
                        __('columns', 'fp-esperienze')
                    ),

                    createElement('div', {
                        style: {
                            fontSize: '12px',
                            color: '#999'
                        }
                    }, 
                        __('Order:', 'fp-esperienze') + ' ' + orderBy + ' (' + order + ')'
                    ),

                    activeFilters.length > 0 && createElement('div', {
                        style: {
                            fontSize: '12px',
                            color: 'var(--fp-brand-primary, #ff6b35)',
                            marginTop: '10px',
                            fontWeight: '500'
                        }
                    }, 
                        __('Active filters:', 'fp-esperienze') + ' ' + activeFilters.join(', ')
                    ),

                    createElement('div', {
                        style: {
                            fontSize: '11px',
                            color: '#999',
                            marginTop: '15px',
                            fontStyle: 'italic'
                        }
                    }, __('This block will display the experience archive on the frontend', 'fp-esperienze'))
                )
            );
        },

        save: function() {
            // Server-side rendering - no save function needed
            return null;
        }
    });

})();