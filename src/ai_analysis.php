<?php
declare(strict_types=1);

/** Code-first health analysis helpers. AI may rewrite wording but never determines status. */
function carenest_metric_key(string $name): string
{
    $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? $name));
    return trim(str_replace(['bp', 'spo2', 'hba1c'], ['blood pressure', 'oxygen saturation', 'hemoglobin a1c'], $key));
}

function carenest_reference_range(string $name): ?array
{
    $key = carenest_metric_key($name);
    $ranges = [
        'heart rate' => [60, 100, 'bpm'], 'oxygen saturation' => [95, 100, '%'],
        'temperature' => [36.1, 37.2, '°C'], 'bmi' => [18.5, 24.9, ''],
        'blood glucose fasting' => [70, 99, 'mg/dL'], 'fasting blood glucose' => [70, 99, 'mg/dL'],
        'random blood glucose' => [70, 140, 'mg/dL'], 'blood glucose random' => [70, 140, 'mg/dL'],
        'hemoglobin a1c' => [4.5, 5.7, '%'], 'hemoglobin' => [12, 17.5, 'g/dL'],
        'total cholesterol' => [125, 200, 'mg/dL'], 'cholesterol total' => [125, 200, 'mg/dL'],
        'ldl cholesterol' => [0, 100, 'mg/dL'], 'hdl cholesterol' => [40, 80, 'mg/dL'],
        'triglycerides' => [50, 150, 'mg/dL'], 'creatinine' => [0.6, 1.1, 'mg/dL'],
        'tsh' => [0.5, 4.5, 'mIU/L'], 'vitamin d' => [30, 100, 'ng/mL'],
        'vitamin b12' => [200, 900, 'pg/mL'], 'platelets' => [150, 450, '10^9/L'],
        'white blood cell count' => [4, 11, '10^9/L'], 'red blood cell count' => [3.8, 5.9, '10^12/L'],
    ];
    foreach ($ranges as $label => $range) if ($key === $label || str_contains($key, $label)) return ['min' => $range[0], 'max' => $range[1], 'unit' => $range[2]];
    return null;
}

function carenest_classify_metric(string $name, mixed $value): array
{
    if (!is_numeric($value)) return ['status' => 'unknown', 'label' => 'Not assessed', 'range' => 'Not available'];
    $number = (float)$value; $range = carenest_reference_range($name);
    if (!$range) return ['status' => 'unknown', 'label' => 'Not assessed', 'range' => 'Not available'];
    $width = max(1.0, $range['max'] - $range['min']);
    if ($number < $range['min']) $status = $number >= $range['min'] - $width * .1 ? 'slightly_low' : 'low';
    elseif ($number > $range['max']) $status = $number <= $range['max'] + $width * .1 ? 'slightly_high' : 'high';
    else $status = 'normal';
    return ['status' => $status, 'label' => ucwords(str_replace('_', ' ', $status)), 'range' => $range['min'].'–'.$range['max'].' '.$range['unit']];
}

function carenest_build_analysis(array $metrics): array
{
    $items = [];
    foreach ($metrics as $metric) {
        $name = trim((string)($metric['metric_type'] ?? $metric['name'] ?? ''));
        if ($name === '' || !is_numeric($metric['value'] ?? null)) continue;
        $classification = carenest_classify_metric($name, $metric['value']);
        $items[] = ['name' => $name, 'value' => (float)$metric['value'], 'unit' => (string)($metric['unit'] ?? ''), ...$classification];
    }
    $abnormal = array_values(array_filter($items, static fn(array $item): bool => $item['status'] !== 'normal' && $item['status'] !== 'unknown'));
    $summary = $items === [] ? 'No readable measurements were found.' : ($abnormal === [] ? 'Your recorded measurements are within the usual reference ranges overall.' : 'Some measurements are outside the usual reference ranges. Review the highlighted results with a healthcare professional.');
    return ['items' => $items, 'summary' => $summary, 'has_abnormal' => $abnormal !== []];
}
