import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const readWidgetCss = (): string =>
	readFileSync( resolve( process.cwd(), 'assets/widget.css' ), 'utf8' );

describe( 'widget logical RTL layout', () => {
	it( 'uses logical inline positioning instead of physical left and right offsets', () => {
		const css = readWidgetCss();

		expect( css ).toContain( 'inset-inline-end: 1rem;' );
		expect( css ).toContain( 'inset-inline-start: 1rem;' );
		expect( css ).toContain( 'inset-inline-end: 0;' );
		expect( css ).toContain( 'inset-inline-start: 0;' );
		expect( css ).not.toMatch( /(^|\n)\s*(?:left|right):\s*/ );
	} );
} );
