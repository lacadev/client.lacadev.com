export function replaceArrayItem( items = [], index, nextItem ) {
	return items.map( ( item, itemIndex ) =>
		itemIndex === index ? nextItem : item
	);
}

export function patchArrayItem( items = [], index, patch ) {
	return items.map( ( item, itemIndex ) =>
		itemIndex === index ? { ...item, ...patch } : item
	);
}

export function removeArrayItem( items = [], index ) {
	return items.filter( ( _, itemIndex ) => itemIndex !== index );
}

export function appendArrayItem( items = [], nextItem, limit = 12 ) {
	if ( items.length >= limit ) {
		return items;
	}

	return [ ...items, nextItem ];
}
