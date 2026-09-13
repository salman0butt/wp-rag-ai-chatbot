export {};

type BlockEditorEvent = {
	target: { value: string };
};

type BlockEditorProps = {
	attributes: { bot: string };
	setAttributes: ( attributes: { bot: string } ) => void;
};

type BlockSettings = {
	attributes: { bot: { type: string; default: string } };
	edit: ( props: BlockEditorProps ) => unknown;
	save: () => null;
};

type WordPressBlockWindow = {
	wp: {
		blocks: {
			registerBlockType: (
				name: string,
				settings: BlockSettings
			) => void;
		};
		element: {
			createElement: (
				type: string,
				props: Record< string, unknown >
			) => unknown;
		};
	};
};

const wordpress = ( window as unknown as WordPressBlockWindow ).wp;

wordpress.blocks.registerBlockType( 'wp-rag-ai-chatbot/chatbot', {
	attributes: {
		bot: { type: 'string', default: '' },
	},
	edit: ( { attributes, setAttributes }: BlockEditorProps ) =>
		wordpress.element.createElement( 'input', {
			type: 'text',
			value: attributes.bot,
			placeholder: 'Bot ID',
			'aria-label': 'Bot ID',
			onChange: ( event: BlockEditorEvent ) =>
				setAttributes( { bot: event.target.value } ),
		} ),
	save: () => null,
} );
