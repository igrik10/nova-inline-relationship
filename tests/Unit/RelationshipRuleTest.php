<?php

namespace Tests\Unit;

use Orchestra\Testbench\TestCase;
use KirschbaumDevelopment\NovaInlineRelationship\Rules\RelationshipRule;

class RelationshipRuleTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'AckfSECXIvnK5r28GVIWUAxmbBSjTsmF');
    }

    public function testItFailsWhenEnvelopeValueIsMissingRequiredChild()
    {
        $rule = new RelationshipRule(
            ['pre_notification_pallets.*.quantity' => 'required'],
            [],
            ['pre_notification_pallets.*.quantity' => 'Quantity']
        );

        $payload = [
            ['values' => ['quantity' => ''], 'modelId' => 0],
        ];

        $this->assertFalse($rule->passes('pre_notification_pallets', $payload));

        $messages = $rule->message();

        $this->assertArrayHasKey('pre_notification_pallets.0.values.quantity', $messages);
        $this->assertStringContainsString('Quantity', $messages['pre_notification_pallets.0.values.quantity']);
    }

    public function testItPassesWhenEnvelopeValueSatisfiesRequiredChild()
    {
        $rule = new RelationshipRule(
            ['pre_notification_pallets.*.quantity' => 'required'],
            [],
            []
        );

        $payload = [
            ['values' => ['quantity' => '5'], 'modelId' => 0],
            ['values' => ['quantity' => '10'], 'modelId' => 1],
        ];

        $this->assertTrue($rule->passes('pre_notification_pallets', $payload));
        $this->assertSame([], $rule->message());
    }

    public function testItStillSupportsFlatPayloadShape()
    {
        $rule = new RelationshipRule(
            ['bills.*.amount' => 'required'],
            [],
            []
        );

        $valid = [
            ['amount' => '100'],
        ];

        $this->assertTrue($rule->passes('bills', $valid));

        $invalid = [
            ['amount' => ''],
        ];

        $this->assertFalse($rule->passes('bills', $invalid));
        $this->assertArrayHasKey('bills.0.amount', $rule->message());
    }

    public function testItHandlesJsonEncodedPayload()
    {
        $rule = new RelationshipRule(
            ['items.*.amount' => 'required'],
            [],
            []
        );

        $json = json_encode([
            ['values' => ['amount' => ''], 'modelId' => 0],
        ]);

        $this->assertFalse($rule->passes('items', $json));
        $this->assertArrayHasKey('items.0.values.amount', $rule->message());
    }
}
