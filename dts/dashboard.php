<?php
// dts/dashboard.php
messageAlert($showAlert, $message, $success);
contentTitleWithModal('Dashboard', uri() . '/modules/documents/save-document-dialog.php', 'New Document', 'fa-plus');
?>

<div class="row mt-4">
	<?php
	card('Incoming Documents', customUri('dts', 'Incoming Documents'), 'fa-file-download', 'primary', $countIncoming);
	card('Pending Documents', customUri('dts', 'Pending Documents'), 'fa-history', 'success', $countPending);
	card('Outgoing Documents', customUri('dts', 'Outgoing Documents'), 'fa-file-upload', 'info', $countOutgoing);
	card('Ongoing Documents', customUri('dts', 'Ongoing Documents'), 'fa-tasks', 'warning', $countOngoing);
	card('Completed Documents', customUri('dts', 'Completed Documents'), 'fa-check-circle', 'secondary');

	if (!$isSchoolPortal) {
		card('Received Documents', customUri('dts', 'Received Documents'), 'fa-hand-holding-medical', 'dark');
	}

	card('Canceled Documents', customUri('dts', 'Canceled Documents'), 'fa-trash-alt', 'danger');
	card('Transactions Summary', customUri('dts', 'Transactions Summary'), 'fa-chart-bar', 'primary');
	?>
</div>

<?php if (false): ?>
	<script src="<?= uri() ?>/assets/vendor/chart.js/Chart.min.js"></script>
	<script src="<?= uri() ?>/assets/vendor/chart.js/chartjs-plugin-datalabels.min.js"></script>
	<script src="<?= uri() ?>/assets/js/chart-custom.js?v=<?= VERSION ?>"></script>

	<div class="row">
		<div class="col-xl-12 col-lg-12 mb-4">
			<div class="card shadow">
				<div class="card-header py-3">
					<h6 class="m-0 font-weight-bold text-primary text-uppercase">Section Transactions</h6>
				</div>
				<div class="card-body">
					<div class="chart-bar h-auto">
						<canvas id="section-transactions-chart"></canvas>
						<script>
							<?php
							$sectionsData = [];
							$sections = sections();
							foreach ($sections as $section) {
								// Single query call per loop iteration
								$counts = stationTransactionCounts($section['id']);

								$sectionsData[] = [
									'label' => $section['name'],
									'incoming' => $counts['incoming'],
									'pending' => $counts['pending'],
									'outgoing' => $counts['outgoing'],
									'ongoing' => $counts['ongoing']
								];
							}
							?>
							generateMultiSeriesBarChart(<?= json_encode($sectionsData) ?>, 'section-transactions-chart');
						</script>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-xl-12 col-lg-12 mb-4">
			<div class="card shadow">
				<div class="card-header py-3">
					<h6 class="m-0 font-weight-bold text-primary text-uppercase">School Transactions</h6>
				</div>
				<div class="card-body">
					<div class="chart-bar h-auto">
						<canvas id="school-transactions-chart"></canvas>
						<script>
							<?php
							$schoolsData = [];
							$schools = schoolsExcept(divisionID());
							foreach ($schools as $school) {
								$counts = stationTransactionCounts($school['alias']);

								$schoolsData[] = [
									'label' => $school['name'] . ' (' . $school['alias'] . ')',
									'incoming' => $counts['incoming'],
									'pending' => $counts['pending'],
									'outgoing' => $counts['outgoing'],
									'ongoing' => $counts['ongoing']
								];
							}
							?>
							generateMultiSeriesBarChart(<?= json_encode($schoolsData) ?>, 'school-transactions-chart');
						</script>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif ?>