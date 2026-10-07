<?php

namespace GovStore\Theming\Lab;

/**
 * Static fixture data for the Theme Lab. Never reads real assets, users or offices.
 */
final class LabFixtures
{
    public function documents(): array
    {
        return [
            ['no' => 'GRN-2026-00412', 'type' => 'Goods Receipt', 'office' => 'Dhaka District Store', 'date' => '2026-10-02', 'items' => 14, 'value' => 482500.00, 'status' => 'posted'],
            ['no' => 'GRN-2026-00413', 'type' => 'Goods Receipt', 'office' => 'Dhaka District Store', 'date' => '2026-10-03', 'items' => 3, 'value' => 18250.50, 'status' => 'ready'],
            ['no' => 'ISS-2026-01120', 'type' => 'Issue Voucher', 'office' => 'Gazipur Upazila Office', 'date' => '2026-10-04', 'items' => 6, 'value' => 9600.00, 'status' => 'draft'],
            ['no' => 'TRF-2026-00077', 'type' => 'Transfer', 'office' => 'Narayanganj Office', 'date' => '2026-10-05', 'items' => 2, 'value' => 120000.00, 'status' => 'approved'],
            ['no' => 'ADJ-2026-00019', 'type' => 'Adjustment', 'office' => 'Dhaka District Store', 'date' => '2026-10-06', 'items' => 1, 'value' => 1500.00, 'status' => 'cancelled'],
            ['no' => 'REQ-2026-00381', 'type' => 'Requisition', 'office' => 'Savar Upazila Office', 'date' => '2026-10-06', 'items' => 9, 'value' => 33750.00, 'status' => 'rejected'],
        ];
    }

    public function assets(): array
    {
        return [
            ['tag' => 'NAR-000184', 'name' => 'Dell Latitude 5440', 'model' => 'Latitude 5440', 'user' => 'Rahima Akter', 'status' => 'deployed'],
            ['tag' => 'NAR-000185', 'name' => 'HP LaserJet M404', 'model' => 'LaserJet Pro', 'user' => '—', 'status' => 'deployable'],
            ['tag' => 'NAR-000186', 'name' => 'Cisco 2960 Switch', 'model' => 'Catalyst 2960', 'user' => '—', 'status' => 'pending'],
            ['tag' => 'NAR-000187', 'name' => 'Epson Projector', 'model' => 'EB-X51', 'user' => '—', 'status' => 'undeployable'],
            ['tag' => 'NAR-000188', 'name' => 'Lenovo ThinkCentre', 'model' => 'M70q', 'user' => '—', 'status' => 'archived'],
        ];
    }

    public function statusTabs(): array
    {
        return [
            ['key' => 'all', 'label' => 'All', 'count' => 128, 'href' => '#all'],
            ['key' => 'draft', 'label' => 'Draft', 'count' => 12, 'href' => '#draft'],
            ['key' => 'ready', 'label' => 'Ready', 'count' => 7, 'href' => '#ready'],
            ['key' => 'posted', 'label' => 'Posted', 'count' => 109, 'href' => '#posted'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => 0, 'href' => '#cancelled'],
        ];
    }

    public function steps(string $current = 'ready'): array
    {
        $order = ['draft', 'ready', 'posted'];
        $index = array_search($current, $order, true);

        return array_map(fn ($key, $i) => ['key' => $key, 'label' => ucfirst($key), 'state' => $i < $index ? 'done' : ($i === $index ? 'current' : 'upcoming')], $order, array_keys($order));
    }

    public function receiptLines(): array
    {
        return [
            ['item' => 'A4 Paper 80gsm (ream)', 'unit' => 'Ream', 'qty' => 200, 'rate' => 450.00],
            ['item' => 'Toner HP 59A', 'unit' => 'Piece', 'qty' => 12, 'rate' => 8950.00],
            ['item' => 'Office Chair (Executive)', 'unit' => 'Piece', 'qty' => 4, 'rate' => 14500.00],
        ];
    }

    public function keyValues(): array
    {
        return [
            ['label' => 'Document No.', 'value' => 'GRN-2026-00413'],
            ['label' => 'Supplier', 'value' => 'Bangladesh Office Supplies Ltd.'],
            ['label' => 'Received at', 'value' => 'Dhaka District Store'],
            ['label' => 'Received on', 'value' => '03 Oct 2026'],
            ['label' => 'Challan', 'value' => 'CH-88213'],
            ['label' => 'Committee', 'value' => 'Receipt & Inspection Committee (3 members)'],
        ];
    }

    public function timeline(): array
    {
        return [
            ['at' => '03 Oct 2026 10:14', 'by' => 'Md. Karim', 'action' => 'created', 'target' => 'GRN-2026-00413'],
            ['at' => '03 Oct 2026 11:02', 'by' => 'Md. Karim', 'action' => 'added 3 lines to', 'target' => 'GRN-2026-00413'],
            ['at' => '03 Oct 2026 15:40', 'by' => 'Nasrin Sultana', 'action' => 'marked ready', 'target' => 'GRN-2026-00413'],
        ];
    }

    public function kpis(): array
    {
        return [
            ['label' => 'Assets in service', 'bn' => 'সেবায় সম্পদ', 'value' => '12,486', 'delta' => '+2.1%', 'icon' => 'fa-laptop', 'tone' => 'primary'],
            ['label' => 'Pending receipts', 'bn' => 'অপেক্ষমাণ প্রাপ্তি', 'value' => '37', 'delta' => '-8', 'icon' => 'fa-truck-ramp-box', 'tone' => 'warning'],
            ['label' => 'Stock value (BDT)', 'bn' => 'মজুদ মূল্য', 'value' => '৳ 4.82 Cr', 'delta' => '+0.4%', 'icon' => 'fa-coins', 'tone' => 'success'],
            ['label' => 'Overdue audits', 'bn' => 'বকেয়া নিরীক্ষা', 'value' => '5', 'delta' => '+1', 'icon' => 'fa-clipboard-check', 'tone' => 'danger'],
        ];
    }

    public function chart(): array
    {
        return [
            'labels' => ['Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Sylhet', 'Barishal', 'Rangpur', 'Mymensingh'],
            'series' => [412, 288, 190, 176, 120, 98, 143, 87],
        ];
    }

    public function bengaliSample(): string
    {
        return 'জাতীয় সম্পদ নিবন্ধন — দৈনন্দিন কাজের জন্য স্পষ্ট, নির্ভরযোগ্য ও সহজ।';
    }

    public function englishSample(): string
    {
        return 'National Asset Register — clear, dependable and easy for daily work.';
    }
}
