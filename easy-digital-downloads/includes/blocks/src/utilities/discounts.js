import { useState, useEffect, useMemo } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * A searchable discount code selector for use in block editor panels.
 * Calls the edd_discount_search AJAX endpoint with live search.
 *
 * @param {Object}   props
 * @param {string}   props.value    The currently selected discount code.
 * @param {Function} props.onChange Called with the new discount code when selection changes.
 */
export const DiscountCombobox = ( { value, onChange } ) => {
	const [ options, setOptions ] = useState( [] );

	const fetchDiscounts = async ( search ) => {
		const params = new URLSearchParams( {
			action: 'edd_discount_search',
			s: search ?? '',
			filter_invalid: true,
		} );

		try {
			const discounts = await apiFetch( {
				url: `${ globalThis.ajaxurl }?${ params.toString() }`,
			} );
			setOptions(
				Object.values( discounts ).map( ( d ) => ( {
					value: d.code,
					label: `${ d.code } — ${ d.name }`,
				} ) )
			);
		} catch ( e ) {
			console.error( e );
			setOptions( [] );
		}
	};

	const debouncedFetch = useDebounce( fetchDiscounts, 300 );

	useEffect( () => {
		fetchDiscounts( '' );
	}, [] );

	// Always ensure the saved value is present in the options list so
	// ComboboxControl can display it while the async fetch is in flight
	// or if the code is no longer returned by the search endpoint.
	const allOptions = useMemo( () => {
		if ( value && ! options.some( ( o ) => o.value === value ) ) {
			return [ { value, label: value }, ...options ];
		}
		return options;
	}, [ value, options ] );

	return (
		<ComboboxControl
			label={ __( 'Discount Code', 'easy-digital-downloads' ) }
			help={ __( 'Automatically apply this discount when the item is added to the cart.', 'easy-digital-downloads' ) }
			value={ value || '' }
			options={ allOptions }
			onFilterValueChange={ debouncedFetch }
			onChange={ ( newValue ) => onChange( newValue ?? '' ) }
		/>
	);
};
