<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?php echo e($viewTitle); ?> - <?php echo e($tableLabel); ?></title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; }
        table { width:100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 4px 5px; vertical-align: middle; word-wrap: break-word; }
        thead th { background: #d1fae5; text-align:center; font-weight:700; }
        .sub-head th { background: #ecfdf5; font-weight:700; }
        .col-no { width: 40px; text-align:center; }
        .col-entity { width: 220px; text-align:left; }
        .text-center { text-align:center; }
    </style>
</head>
<body>
    <h1><?php echo e($viewTitle); ?> - <?php echo e($tableLabel); ?></h1>
    <p>Export <?php echo e(strtoupper($exportFormat ?? 'PDF')); ?> | Tahun <?php echo e($selectedYear ?? '-'); ?></p>
    <p><strong>Basis:</strong> <?php echo e($entityHeaderLabel); ?> | <strong>Metrik:</strong> <?php echo e($metricLabel); ?></p>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="col-no">No</th>
                <th rowspan="2" class="col-entity"><?php echo e($entityHeaderLabel); ?></th>
                <th rowspan="1">RKPD/Renja (Tahun <?php echo e($selectedYear ?? '-'); ?>)</th>
                <th rowspan="1">APBD (Tahun <?php echo e($selectedYear ?? '-'); ?>)</th>
                <th colspan="2">Konsistensi RKPD/Renja - APBD</th>
            </tr>
            <tr class="sub-head">
                <th>Jumlah <?php echo e($metricLabel); ?></th>
                <th>Jumlah <?php echo e($metricLabel); ?></th>
                <th>Jumlah <?php echo e($metricLabel); ?> Yang Sama</th>
                <th>Jumlah <?php echo e($metricLabel); ?> Yang Tidak Sama</th>
            </tr>
            <tr class="sub-head">
                <th>(1)</th>
                <th>(2)</th>
                <th>(3)</th>
                <th>(4)</th>
                <th>(5)</th>
                <th>(6)</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td class="text-center"><?php echo e($row['no']); ?></td>
                    <td><?php echo e($row['entitas']); ?></td>
                    <td class="text-center"><?php echo e($row['rkpd_total']); ?></td>
                    <td class="text-center"><?php echo e($row['dpa_total']); ?></td>
                    <td class="text-center"><?php echo e($row['same_rkpd_dpa']); ?></td>
                    <td class="text-center"><?php echo e($row['diff_rkpd_dpa']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" class="text-center">Tidak ada data.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html><?php /**PATH C:\Users\kamin\Documents\Project\simevlap\resources\views/exports/resume_tabel_rkpd_apbd.blade.php ENDPATH**/ ?>