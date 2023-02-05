<?php

namespace App\Http\Livewire\Proses;

use App\Models\Alternatif;
use App\Models\Kriteria;
use Livewire\Component;
use Barryvdh\DomPDF\Facade\Pdf;

class Index extends Component
{
	public function render()
	{
		$alternatifs = $this->proses();
		return view('livewire.proses.index', compact('alternatifs'));
	}

	public function print()
	{
		// abaikan garis error di bawah 'Pdf' jika ada.
		$pdf = Pdf::loadView('laporan.cetak', ['data' => $this->proses()])->output();
		// return $pdf->download('Laporan.pdf');
		return response()->streamDownload(fn () => print($pdf), 'Laporan.pdf');
	}

	// proses metode PSI
	public function proses()
	{
		$alternatifs = Alternatif::orderBy('kode')->get();
		$kriterias = Kriteria::orderBy('kode')->get('type')->toArray();
		// dd($kriterias);

		// penentuan matriks keputusan
		$Xij = [];
		foreach ($alternatifs as $ka => $alt) {
			foreach ($alt->kriteria as $kk => $krit) {
				$Xij[$ka][$kk] = $krit->pivot->nilai;
			}
		}

		// normalisasi matriks keputusan
		$rows = count($Xij);
		$cols = count($Xij[0]);
		$Nij = [];
		for ($j = 0; $j < $cols; $j++) {
			$xj = [];
			for ($i = 0; $i < $rows; $i++) {
				$xj[] = $Xij[$i][$j];
			}

			$divisor = max($xj);
			$cost = false;
			if ($kriterias[$j]['type'] == false) {
				$cost = true;
				$divisor = min($xj);
			}

			foreach ($xj as $kj => $x) {
				$Nij[$kj][$j] = $cost ? ($divisor / $x) : ($x / $divisor);
			}
		}

		// menjumlahkan elemen tiap kolom matriks
		$EN = [];
		for ($i = 0; $i < $cols; $i++) {
			$jumlah = 0;
			for ($j = 0; $j < $rows; $j++) {
				$jumlah += $Nij[$j][$i];
			}
			$EN[] = $jumlah;
		}

		// hitung nilai mean
		$N = [];
		foreach ($EN as $e) {
			$N[] = $e / $rows;
		}

		// hitung variasi preferensi
		$Tj = [];
		for ($i = 0; $i < $cols; $i++) {
			for ($j = 0; $j < $rows; $j++) {
				$Tj[$j][$i] = pow($Nij[$j][$i] - $N[$i], 2);
			}
		}

		// hitung total tiap kriteria
		$TTj = [];
		for ($i = 0; $i < $cols; $i++) {
			$jumlah = 0;
			for ($j = 0; $j < $rows; $j++) {
				$jumlah += $Tj[$j][$i];
			}
			$TTj[] = $jumlah;
		}

		// menentukan penyimpangan nilai preferensi
		$Omega = [];
		foreach ($TTj as  $ttj) {
			$Omega[] = 1 - $ttj;
		}

		// total penyimpangan nilai preferensi
		$EOmega = array_sum($Omega);

		// menghitung kriteria bobot
		$Wj = [];
		foreach ($Omega as $o) {
			$Wj[] = $o / $EOmega;
		}

		// menghitung PSI
		$ThetaI = [];
		for ($i = 0; $i < $cols; $i++) {
			for ($j = 0; $j < $rows; $j++) {
				$ThetaI[$j][$i] = $Nij[$j][$i] * $Wj[$i];
			}
		}

		// penjumlahan tiap baris proses sebelumnya
		$TThetaI = [];
		foreach ($ThetaI as $theta) {
			$TThetaI[] = array_sum($theta);
		}

		foreach ($alternatifs as $key => $alternatif) {
			$alternatif->nilai = round($TThetaI[$key], 4);
		}

		return $alternatifs;
	}
}