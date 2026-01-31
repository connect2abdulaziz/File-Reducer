<?php

namespace Blocksy\Extensions\NewsletterSubscribe;

class KlaviyoProvider extends Provider {
	public function __construct() {
	}

	public function fetch_lists($api_key, $api_url = '') {
		$response = wp_remote_get(
			'https://a.klaviyo.com/api/lists',
			[
				'timeout' => 2,
				'headers' => [
					'Authorization' => 'Klaviyo-API-Key ' . $api_key,
					'accept' => 'application/json',
					'revision' => '2025-10-15'
				]
			]
		);

		if (! is_wp_error($response)) {
			if (200 !== wp_remote_retrieve_response_code($response)) {
				return 'api_key_invalid';
			}

			$body = json_decode(wp_remote_retrieve_body($response), true);

			if (! $body) {
				return 'api_key_invalid';
			}

			if (! isset($body['data'])) {
				return 'api_key_invalid';
			}
		}

		return array_map(function($list) {
			return [
				'name' => $list['attributes']['name'],
				'id' => $list['id'],
			];
		}, $body['data']);
	}

	public function get_form_url_and_gdpr_for($maybe_custom_list = null) {
		return [
			'form_url' => '#',
			'has_gdpr_fields' => false,
			'provider' => 'klaviyo'
		];
	}

	public function subscribe_form($args = []) {
		$args = wp_parse_args($args, [
			'email' => '',
			'name' => '',
			'group' => ''
		]);

		$settings = $this->get_settings();

		$name_parts = $this->maybe_split_name($args['name']);
		$fname = $name_parts['first_name'];
		$lname = $name_parts['last_name'];

		$list_ids = [$args['group']];

		$subscriber = [
			'email' => $args['email'],
			'first_name' => $fname
		];

		if (! empty($lname)) {
			$subscriber['last_name'] = $lname;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		$curl = curl_init();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://a.klaviyo.com/api/profile-import',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode([
				'data' => [
					'type' => 'profile',
					'attributes' => $subscriber,
				]
			]),
			CURLOPT_HTTPHEADER => array(
				'Authorization: Klaviyo-API-Key ' . $settings['api_key'],
				'accept: application/vnd.api+json',
				'content-type: application/vnd.api+json',
				'revision: 2025-10-15'
			),
		));

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$response = curl_exec($curl);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		$err = curl_error($curl);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
		curl_close($curl);

		if ($err) {
			return [
				'result' => 'no',
				'error' => $err
			];
		}

		$curl = curl_init();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://a.klaviyo.com/api/lists/' . $args['group'] . '/relationships/profiles',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode([
				'data' => [
					[
						'type' => 'profile',
						'id' => json_decode($response, true)['data']['id']
					]
				]
			]),
			CURLOPT_HTTPHEADER => array(
				'Authorization: Klaviyo-API-Key ' . $settings['api_key'],
				'accept: application/vnd.api+json',
				'content-type: application/vnd.api+json',
				'revision: 2025-10-15'
			),
		));

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$response = curl_exec($curl);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		$err = curl_error($curl);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
		curl_close($curl);

		if ($err) {
			return [
				'result' => 'no',
				'error' => $err
			];
		}

		return [
			'result' => 'yes',
			'message' => __('Thank you for subscribing to our newsletter!', 'blocksy-companion')
		];
	}
}

