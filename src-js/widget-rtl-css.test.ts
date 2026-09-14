type FsModule = {
	readFileSync: ( path: string, encoding: 'utf8' ) => string;
};

const { readFileSync } = jest.requireActual< FsModule >( 'fs' );

const readWidgetCss = (): string => readFileSync( 'assets/widget.css', 'utf8' );

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
