/**
 * EDD Checkout box container tree-walk and id helpers for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { EDD_TYPE, SUPPORTED_WIDGETS, REQUIRED_WIDGETS } from './patterns';

/**
 * The payment-info section widget type — the re-add resolver's layout anchor.
 *
 * Payment-info is delete-locked, so it is present in every box regardless of the
 * active layout; its ancestor chain in the box model identifies that layout and
 * the container a re-added section belongs in. Mirrors the section-type constant
 * in patterns.js (which does not export it).
 *
 * @since 3.7.0
 * @type {string}
 */
const PAYMENT_INFO_WIDGET = 'edd-checkout-payment-info';

/**
 * The cart section widget type.
 *
 * The re-add resolver routes the cart differently from the discount form, so it
 * has to recognise the cart widget type. Mirrors the section-type constant in
 * patterns.js (which does not export it).
 *
 * @since 3.7.0
 * @type {string}
 */
const CART_WIDGET = 'edd-checkout-cart';

/**
 * Whether the Elementor editor internals required by this module are present.
 *
 * A single feature-detection check: if Elementor's internal shape ever changes
 * such that the elements manager or the command runner is missing, the
 * integration is a no-op rather than throwing.
 *
 * @since 3.7.0
 * @return {boolean} True when the required internals are available.
 */
const hasRequiredInternals = () => {
	const elementor = globalThis.elementor;

	return Boolean(
		typeof elementor?.elementsManager?.getElementTypeClass === 'function' &&
		typeof elementor?.elementsManager?.registerElementType === 'function'
	);
};

/**
 * Walk up to the document-root container from any container.
 *
 * Climbs the `parent` chain (the same chain findEnclosingBox uses) until it runs
 * out of ancestors, so the last non-null container is the document root whose
 * model holds every top-level element.
 *
 * @since 3.7.0
 * @param {Object} container The container to start from.
 * @return {?Object} The document-root container, or null.
 */
const rootContainer = ( container ) => {
	let current = container;

	while ( current?.parent ) {
		current = current.parent;
	}

	return current ?? null;
};

/**
 * Count the edd-checkout-box elements in a document element collection.
 *
 * Recurses the collection (a box is never nested in a box, but the walk is
 * defensive) counting every element whose elType is our checkout box.
 *
 * @since 3.7.0
 * @param {Object} collection A Backbone-style elements collection (has `each`).
 * @return {number} The number of checkout boxes found.
 */
const countCheckoutBoxes = ( collection ) => {
	let count = 0;

	collection?.each?.( ( child ) => {
		if ( child.get( 'elType' ) === EDD_TYPE ) {
			count += 1;
		}
		const nested = child.get( 'elements' );
		if ( nested ) {
			count += countCheckoutBoxes( nested );
		}
	} );

	return count;
};

/**
 * Compute which required section widgets are missing from a checkout box.
 *
 * Walks the box container's descendants recursively (via boxContainsType), so a required
 * section nested inside an inner column still counts as present — matching the
 * PHP detection utility, which also recurses. A required widgetType is reported
 * missing only when absent at every depth of the box subtree.
 *
 * @since 3.7.0
 * @param {Object} container The box container (has a model with `elements`).
 * @return {string[]} The required widgetTypes absent from the box.
 */
const getMissingRequired = ( container ) => REQUIRED_WIDGETS.filter(
	( widgetType ) => ! boxContainsType( container, widgetType )
);

/**
 * Resolve the enclosing EDD Checkout box view for a lifecycle event target.
 *
 * Walks up the view tree from the rendered/destroyed element to the nearest
 * ancestor whose model elType is the EDD Checkout box, so a child add/remove
 * refreshes the correct box's overlay. Returns the box view itself when the
 * event target IS the box.
 *
 * @since 3.7.0
 * @param {Object} view The element view from the lifecycle event.
 * @return {?Object} The box view, or null when the target is not inside a box.
 */
const findBoxView = ( view ) => {
	let current = view;

	while ( current ) {
		if ( current.model?.get?.( 'elType' ) === EDD_TYPE ) {
			return current;
		}
		current = current._parent ?? null;
	}

	return null;
};

/**
 * Resolve the box container a picker control view is editing.
 *
 * Elementor passes the edited element's container to every control view as
 * `options.container`; a defensive fallback reads `container` directly.
 *
 * @since 3.7.0
 * @param {Object} view The picker control view.
 * @return {?Object} The box container, or null.
 */
const pickerBox = ( view ) => view?.options?.container ?? view?.container ?? null;

/**
 * Whether a widget type is one of the EDD checkout section widgets we dedupe.
 *
 * The four checkout section types are exactly the deduped set — all four are
 * seeded by every pattern — and a box holds at most one of each.
 *
 * @since 3.7.0
 * @param {string} widgetType The widget type to test.
 * @return {boolean} True when the type is a deduped checkout section.
 */
const isSectionWidget = ( widgetType ) => SUPPORTED_WIDGETS.has( widgetType );

/**
 * Resolve a container's element id (for excluding moved elements from a count).
 *
 * @since 3.7.0
 * @param {Object} container The Elementor container.
 * @return {?string} The element id, or null when unresolved.
 */
const containerId = ( container ) => container?.id ?? container?.model?.get?.( 'id' ) ?? null;

/**
 * Walk up the container tree to the enclosing EDD Checkout box.
 *
 * Starts at the given container (so passing the box itself returns it) and climbs
 * the `parent` chain until it finds an EDD Checkout box or runs out of ancestors.
 * Lets the guards work whether a section is a direct child of the box or nested in
 * an inner column/container inside it.
 *
 * @since 3.7.0
 * @param {Object} container The container to start from.
 * @return {?Object} The enclosing box container, or null when not inside a box.
 */
const findEnclosingBox = ( container ) => {
	let current = container;

	while ( current ) {
		if ( current.model?.get?.( 'elType' ) === EDD_TYPE ) {
			return current;
		}
		current = current.parent ?? null;
	}

	return null;
};

/**
 * Whether a checkout box subtree already contains a widget of the given type.
 *
 * Recurses the box's own descendants (not just direct children, so a section
 * nested in an inner column still counts), skipping any element ids in
 * `excludeIds` — used by the move guard to ignore the element(s) being moved so a
 * plain reorder within the same box is not mistaken for a duplicate.
 *
 * @since 3.7.0
 * @param {Object}   boxContainer The enclosing box container.
 * @param {string}   widgetType   The section widget type to look for.
 * @param {string[]} excludeIds   Element ids to skip (e.g. the moved elements).
 * @return {boolean} True when a matching, non-excluded widget already exists.
 */
const boxContainsType = ( boxContainer, widgetType, excludeIds = [] ) => {
	const walk = ( collection ) => {
		let found = false;

		collection?.each?.( ( child ) => {
			if ( found ) {
				return;
			}
			if ( excludeIds.includes( child.get( 'id' ) ) ) {
				return;
			}
			if ( child.get( 'widgetType' ) === widgetType ) {
				found = true;
				return;
			}
			const nested = child.get( 'elements' );
			if ( nested && walk( nested ) ) {
				found = true;
			}
		} );

		return found;
	};

	return walk( boxContainer?.model?.get?.( 'elements' ) );
};

/**
 * Build the ancestor chain to the first section of a given type within a box.
 *
 * Walks the box's descendants (the boxContainsType walk style) for the first
 * widget of `widgetType` and returns the ordered model nodes from the box's own
 * top-level child down to and including that widget. The chain length identifies
 * the section's nesting depth (1 = box root, 2 = inside a column, 3 = inside a
 * row-column) and its penultimate node is the container directly holding it.
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container to walk.
 * @param {string} widgetType The section widget type to locate.
 * @return {Object[]} The model nodes from top-level child to the section (empty when absent).
 */
const sectionChain = ( box, widgetType ) => {
	const chain = [];

	const walk = ( collection, trail ) => {
		let found = false;

		collection?.each?.( ( child ) => {
			if ( found ) {
				return;
			}
			const next = [ ...trail, child ];
			if ( child.get( 'widgetType' ) === widgetType ) {
				chain.push( ...next );
				found = true;
				return;
			}
			const nested = child.get( 'elements' );
			if ( nested && walk( nested, next ) ) {
				found = true;
			}
		} );

		return found;
	};

	walk( box?.model?.get?.( 'elements' ), [] );

	return chain;
};

/**
 * Build the payment-info ancestor chain within a checkout box.
 *
 * The delete-locked payment-info section is present in every box, so its chain
 * depth identifies the active layout (1 = single-column, 2 = two-column, 3 =
 * cart-top) and its penultimate node is the container directly holding it.
 *
 * @since 3.7.0
 * @param {Object} box The checkout box container to walk.
 * @return {Object[]} The model nodes from top-level child to payment-info (empty when absent).
 */
const paymentInfoChain = ( box ) => sectionChain( box, PAYMENT_INFO_WIDGET );

/**
 * Find the index of a child model (by id) within an elements collection.
 *
 * @since 3.7.0
 * @param {Object} collection A Backbone-style elements collection (has `each`).
 * @param {string} id         The child element id to locate.
 * @return {number} The zero-based index, or -1 when not found.
 */
const childIndexById = ( collection, id ) => {
	let index = -1;
	let current = 0;

	collection?.each?.( ( child ) => {
		if ( index >= 0 ) {
			return;
		}
		if ( child.get( 'id' ) === id ) {
			index = current;
		}
		current += 1;
	} );

	return index;
};

/**
 * Whether a container holds a structural child (a nested container or a checkout
 * section) other than the given branch.
 *
 * Lets the re-add resolver climb out of a wrapper that holds nothing but the cart —
 * a "Your Cart" card, say — where a heading or icon beside the cart does not count,
 * so the discount is re-added as a sibling of the card rather than nested inside it.
 *
 * @since 3.7.0
 * @param {Object} containerModel The container model to inspect.
 * @param {Object} branchModel    The child model to ignore (the cart's branch).
 * @return {boolean} True when another container or section child is present.
 */
const hasOtherStructuralChild = ( containerModel, branchModel ) => {
	const branchId = branchModel.get( 'id' );
	let found = false;

	containerModel.get( 'elements' )?.each?.( ( child ) => {
		if ( found || child.get( 'id' ) === branchId ) {
			return;
		}
		if ( 'container' === child.get( 'elType' ) || isSectionWidget( child.get( 'widgetType' ) ) ) {
			found = true;
		}
	} );

	return found;
};

/**
 * Resolve the target container and insertion index for a re-added section.
 *
 * A re-added cart or discount form must land in the container the active layout
 * places it in — not always the box root. The active layout is inferred from the
 * payment-info section's ancestor chain (payment-info is delete-locked, so it is
 * present in every box) whose depth identifies the layout:
 *
 *   - chain [box, payment-info]              → single-column
 *   - chain [box, column, payment-info]      → two-column
 *   - chain [box, row, column, payment-info] → cart-top-two-column
 *
 * Placement per section:
 *   - discount form → immediately after the cart, at the level where sections sit as
 *     siblings. Usually that is the cart's own container (box root in single-column
 *     and cart-top; the RIGHT column in two-column). When a template nests the cart in
 *     a wrapper that holds only the cart (a "Your Cart" card), the discount belongs
 *     beside that card, so the resolver climbs out of any cart-only wrapper first and
 *     inserts after the branch it climbed to. If the cart has been deleted, it falls
 *     back to the container directly holding payment-info, appended after it.
 *   - cart → the box root at index 0 (the top) in single-column and cart-top, so
 *     it sits above the stacked sections; in two-column, the box's other top-level
 *     column (the one that does not hold payment-info), appended.
 *
 * Degrades safely: if payment-info cannot be found, or the expected column is
 * missing (e.g. an emptied two-column cart column was collapsed away), it returns
 * the box root with no index so the create falls back to a plain box-root append.
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container to add into.
 * @param {string} widgetType The non-required section widget type being re-added.
 * @return {{container: Object, at: ?number}} The target container and insertion index (null appends).
 */
const resolveSectionTarget = ( box, widgetType ) => {
	const fallback = { container: box, at: null };

	const elementor = globalThis.elementor;
	if ( ! box || typeof elementor?.getContainer !== 'function' ) {
		return fallback;
	}

	const chain = paymentInfoChain( box );
	if ( ! chain.length ) {
		return fallback;
	}

	if ( widgetType === CART_WIDGET ) {
		// Two-column: the cart lives in the box's other top-level column — the
		// top-level container that is not the one holding payment-info.
		if ( 2 === chain.length ) {
			const formColumnId = chain[ 0 ].get( 'id' );
			let other = null;
			box.model?.get?.( 'elements' )?.each?.( ( child ) => {
				if ( other ) {
					return;
				}
				if ( 'container' === child.get( 'elType' ) && child.get( 'id' ) !== formColumnId ) {
					other = child;
				}
			} );

			const container = other ? elementor.getContainer( other.get( 'id' ) ) : null;
			return container ? { container, at: null } : fallback;
		}

		// Single-column and cart-top: the box root, at the top.
		return { container: box, at: 0 };
	}

	// Discount form (and any other non-cart section): it belongs immediately after the
	// cart, at the level where checkout sections sit as siblings. When a template nests
	// the cart in its own wrapper — a "Your Cart" card that holds only the cart — the
	// discount is a sibling of that card, not a child of it, so climb out of any wrapper
	// that holds nothing but the cart's branch before choosing the insertion container.
	const cartChain = sectionChain( box, CART_WIDGET );
	if ( cartChain.length ) {
		let anchorIndex = cartChain.length - 1; // the cart itself
		let targetIndex = cartChain.length - 2; // the cart's immediate parent

		while ( targetIndex > 0 && ! hasOtherStructuralChild( cartChain[ targetIndex ], cartChain[ anchorIndex ] ) ) {
			anchorIndex = targetIndex;
			targetIndex -= 1;
		}

		const targetModel = targetIndex >= 0 ? cartChain[ targetIndex ] : null;
		const anchorModel = cartChain[ anchorIndex ];
		const container    = targetModel ? elementor.getContainer( targetModel.get( 'id' ) ) : box;
		const siblings     = ( targetModel ?? box.model )?.get?.( 'elements' );
		const anchorInList = childIndexById( siblings, anchorModel.get( 'id' ) );

		if ( container && anchorInList >= 0 ) {
			return { container, at: anchorInList + 1 };
		}
	}

	// No cart node present (the cart is deletable): fall back to the container
	// directly holding payment-info, appended after it. The penultimate chain node
	// is that parent; when the chain is just payment-info itself (single-column) the
	// parent is the box.
	const parentModel = chain.length >= 2 ? chain[ chain.length - 2 ] : null;
	if ( ! parentModel ) {
		return { container: box, at: null };
	}

	const container = elementor.getContainer( parentModel.get( 'id' ) );
	return container ? { container, at: null } : fallback;
};

/**
 * Normalize a command's container arg(s) to an array.
 *
 * Elementor commands accept either a single `container` or a `containers` array;
 * this collapses both to an array, matching the delete-lock's own reading.
 *
 * @since 3.7.0
 * @param {Object} args The command args.
 * @return {Object[]} The containers array (possibly empty).
 */
const argContainers = ( args ) => args?.containers ?? ( args?.container ? [ args.container ] : [] );

export {
	hasRequiredInternals,
	rootContainer,
	countCheckoutBoxes,
	findBoxView,
	getMissingRequired,
	isSectionWidget,
	containerId,
	findEnclosingBox,
	boxContainsType,
	resolveSectionTarget,
	argContainers,
	pickerBox,
};
