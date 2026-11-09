<html>
<head>
    <meta charset="utf-8" />
    <title>Realisasi IKU</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 6px; font-size: 12px; }
        th { background: #d9d9d9; text-align: center; font-weight: bold; }
        .section-row { background: #ffc000; font-weight: bold; }
        .data-row:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <h3><?php echo e($viewTitle ?? 'Realisasi IKU'); ?> - Tahun <?php echo e($selectedYear ?? ''); ?></h3>
    <table>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Indikator</th>
                <th style="width:120px">Target RKPD <?php echo e($selectedYear ?? ''); ?></th>
                <th style="width:130px">Realisasi Tahun 2025</th>
                <th style="width:130px">Capaian Tahun <?php echo e($selectedYear ?? ''); ?> (%)</th>
                <th style="width:140px">Faktor Pendorong</th>
                <th style="width:140px">Faktor Penghambat</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(!empty($row['is_section'])): ?>
                    <tr class="section-row">
                        <td style="text-align:center"><?php echo e($row['section_no'] ?? ''); ?></td>
                        <td colspan="6"><?php echo e($row['label'] ?? ''); ?></td>
                    </tr>
                <?php else: ?>
                    <tr class="data-row">
                        <td style="text-align:center"><?php echo e($row['no'] ?? ''); ?></td>
                        <td><?php echo e($row['indikator'] ?? '-'); ?></td>
                        <td style="text-align:center"><?php echo e($row['target_rkpd'] ?? '-'); ?></td>
                        <td style="text-align:center"><?php echo e($row['realisasi'] ?? '-'); ?></td>
                        <td style="text-align:center"><?php echo e(isset($row['capaian']) && $row['capaian'] !== null ? number_format($row['capaian'], 2).'%' : '-'); ?></td>
                        <td><?php echo e($row['faktor_pendorong'] ?? '-'); ?></td>
                        <td><?php echo e($row['faktor_penghambat'] ?? '-'); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</body>
</html><?php /**PATH C:\Users\kamin\Documents\Project\simevlap\resources\views/exports/realisasi_iku.blade.php ENDPATH**/ ?>