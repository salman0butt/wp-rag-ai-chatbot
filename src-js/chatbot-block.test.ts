const mockRegisterBlockType = jest.fn();
const mockCreateElement = jest.fn(
	( type: unknown, props: Record< string, unknown > | null ) => ( {
		type,
		props: props ?? {},
	} )
);

type EditorProps = {
	attributes: { bot: string };
	setAttributes: ( attributes: { bot: string } ) => void;
};

type RenderedElement = {
	type: unknown;
	props: Record< string, unknown >;
};

type BlockSettings = {
	attributes: { bot: { type: string; default: string } };
	edit: ( props: EditorProps ) => RenderedElement;
	save: () => null;
};

describe( 'chatbot Gutenberg block editor adapter', () => {
	beforeEach( () => {
		jest.resetModules();
		mockRegisterBlockType.mockClear();
		mockCreateElement.mockClear();
		Object.defineProperty( window, 'wp', {
			configurable: true,
			writable: true,
			value: {
				blocks: { registerBlockType: mockRegisterBlockType },
				element: { createElement: mockCreateElement },
			},
		} );
	} );

	afterEach( () => {
		Reflect.deleteProperty( window, 'wp' );
	} );

	it( 'registers one bounded bot-id editor and stays dynamic on save', () => {
		jest.isolateModules( () => {
			jest.requireActual( './chatbot-block' );
		} );

		expect( mockRegisterBlockType ).toHaveBeenCalledTimes( 1 );
		const [ name, settings ] = mockRegisterBlockType.mock.calls[ 0 ] as [
			string,
			BlockSettings,
		];

		expect( name ).toBe( 'wp-rag-ai-chatbot/chatbot' );
		expect( settings.attributes ).toEqual( {
			bot: { type: 'string', default: '' },
		} );

		const setAttributes = jest.fn();
		const editor = settings.edit( {
			attributes: { bot: '0123456789abcdef0123456789abcdef' },
			setAttributes,
		} );

		expect( editor.type ).toBe( 'input' );
		expect( editor.props ).toMatchObject( {
			type: 'text',
			value: '0123456789abcdef0123456789abcdef',
			placeholder: 'Bot ID',
			'aria-label': 'Bot ID',
		} );

		const onChange = editor.props.onChange as ( event: {
			target: { value: string };
		} ) => void;
		onChange( { target: { value: 'fedcba9876543210fedcba9876543210' } } );
		expect( setAttributes ).toHaveBeenCalledWith( {
			bot: 'fedcba9876543210fedcba9876543210',
		} );
		expect( settings.save() ).toBeNull();
	} );
} );
