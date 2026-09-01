import { pluginIdentity } from './index';

describe( 'pluginIdentity', () => {
	it( 'uses the canonical plugin slug and development version', () => {
		expect( pluginIdentity ).toEqual( {
			slug: 'wp-rag-ai-chatbot',
			version: '0.1.0-dev',
		} );
	} );
} );
