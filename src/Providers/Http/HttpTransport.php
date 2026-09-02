<?php
/**
 * Provider HTTP transport contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Http;

/**
 * Executes one provider HTTP request without retry policy.
 */
interface HttpTransport {
	/**
	 * Send one provider HTTP request.
	 *
	 * @param HttpRequest $request Provider HTTP request.
	 * @throws HttpTransportException When the network request fails.
	 */
	public function send( HttpRequest $request ): HttpResponse;
}
