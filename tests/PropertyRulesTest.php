<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PropertyRulesTest extends TestCase
{
    #[Test]
    public function it_normalizes_equivalent_addresses_for_duplicate_checks(): void
    {
        $a = Property_rules::address(['address_1' => '10 Southeast River Drive', 'city' => 'Saint Paul', 'state' => 'Minnesota', 'zip' => '55102']);
        $b = Property_rules::address(['address_1' => '10 SE River Dr.', 'city' => 'St Paul', 'state' => 'MN', 'zip' => '55102', 'label' => 'Second property']);
        self::assertSame(Property_rules::hash($a), Property_rules::hash($b));
        $c = Property_rules::address(['address_1' => '5 Main Street', 'city' => 'Albany', 'state' => 'New York', 'zip' => '12207']);
        $d = Property_rules::address(['address_1' => '5 Main St', 'city' => 'Albany', 'state' => 'NY', 'zip' => '12207']);
        self::assertSame(Property_rules::hash($c), Property_rules::hash($d));
    }

    #[Test]
    public function it_keeps_previously_stored_address_hashes(): void
    {
        // Values produced before full state names were added; stored address_hash rows depend on them.
        $florida = 'dad296ca92f46e89832d5ba1517316832065150e6014ab879bbd61e6e8e42e84';
        self::assertSame($florida, Property_rules::hash(['address_1' => '100 Florida Avenue', 'city' => 'Springfield', 'state' => 'Florida', 'zip' => '62701', 'country' => 'US']));
        self::assertSame($florida, Property_rules::hash(['address_1' => '100 Florida Ave.', 'city' => 'Springfield', 'state' => 'FL', 'zip' => '62701', 'country' => 'US']));
        self::assertSame('ceaa3a51522b2e5c5b6f9d4015b3622e964c31b1595ce249c3101fb5a716feef', Property_rules::hash(['address_1' => '25 Washington Street', 'address_2' => 'Unit 4', 'city' => 'Saint Paul', 'state' => 'MN', 'zip' => '55102', 'country' => 'US']));
    }

    #[Test]
    public function it_rejects_incomplete_addresses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Property_rules::address(['address_1' => '']);
    }

    #[Test]
    public function it_rejects_markup_in_addresses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Property_rules::address(['address_1' => '<script>']);
    }

    #[Test]
    public function it_groups_interleaved_lines_without_changing_totals_or_within_group_order(): void
    {
        $a     = json_encode(['address_1' => 'One']);
        $b     = json_encode(['address_1' => 'Two']);
        $items = [(object) ['item_service_property_id' => 1, 'item_service_address' => $a, 'item_id' => 1, 'item_total' => '300.00'], (object) ['item_service_property_id' => 2, 'item_service_address' => $b, 'item_id' => 2, 'item_total' => '250.00'], (object) ['item_service_property_id' => 1, 'item_service_address' => $a, 'item_id' => 3, 'item_total' => '75.00']];
        $g     = Property_rules::groups($items);
        self::assertCount(2, $g);
        self::assertSame([1, 3], array_column($g[0]['items'], 'item_id'));
        self::assertEquals(625, array_sum(array_column($g, 'total')));
    }

    #[Test]
    public function it_preserves_negative_credit_totals(): void
    {
        $g = Property_rules::groups([(object) ['item_service_property_id' => 1, 'item_service_address' => '{}', 'item_total' => '-125.25']]);
        self::assertEquals(-125.25, $g[0]['total']);
    }
}
