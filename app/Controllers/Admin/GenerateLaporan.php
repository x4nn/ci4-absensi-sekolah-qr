<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use DateTime;

use App\Models\GuruModel;
use App\Models\KelasModel;
use App\Models\PresensiGuruModel;
use App\Models\SiswaModel;
use App\Models\PresensiSiswaModel;
use CodeIgniter\I18n\Time;
use DateInterval;
use DatePeriod;

class GenerateLaporan extends BaseController
{
   protected SiswaModel $siswaModel;
   protected KelasModel $kelasModel;

   protected GuruModel $guruModel;

   protected PresensiSiswaModel $presensiSiswaModel;
   protected PresensiGuruModel $presensiGuruModel;

   public function __construct()
   {
      $this->siswaModel = new SiswaModel();
      $this->kelasModel = new KelasModel();

      $this->guruModel = new GuruModel();

      $this->presensiSiswaModel = new PresensiSiswaModel();
      $this->presensiGuruModel = new PresensiGuruModel();
   }

   public function index()
   {
      $siswa = $this->siswaModel->getAllSiswaWithKelas();
      $kelas = $this->kelasModel->getAllKelas();
      $guru = $this->guruModel->getAllGuru();

      $siswaPerKelas = [];

      foreach ($kelas as $value) {
         array_push($siswaPerKelas, $this->siswaModel->getSiswaByKelas($value['id_kelas']));
      }

      $data = [
         'title' => 'Generate Laporan',
         'ctx' => 'laporan',
         'siswaPerKelas' => $siswaPerKelas,
         'kelas' => $kelas,
         'guru' => $guru
      ];

      return view('admin/generate-laporan/generate-laporan', $data);
   }

   public function generateLaporanSiswa()
   {
      $idKelas = $this->request->getVar('kelas');
      $siswa = $this->siswaModel->getSiswaByKelas($idKelas);

      if (empty($siswa)) {
         session()->setFlashdata([
            'msg' => 'Data siswa kosong!',
            'error' => true
         ]);
         return redirect()->to('/admin/laporan');
      }

      $kelas = $this->kelasModel->where(['id_kelas' => $idKelas])->first();

      $bulan = $this->request->getVar('tanggalSiswa');

      // hari pertama dalam 1 bulan
      $begin = new DateTime($bulan);
      // tanggal terakhir dalam 1 bulan
      $end = (new DateTime($begin->format('Y-m-t')))->modify('+1 day');
      // interval 1 hari
      $interval = DateInterval::createFromDateString('1 day');
      // buat array dari semua hari di bulan
      $period = new DatePeriod($begin, $interval, $end);

      $arrayTanggal = [];
      $dataAbsen = [];

      foreach ($period as $value) {
         // kecualikan hari sabtu dan minggu
         if (!($value->format('D') == 'Sat' || $value->format('D') == 'Sun')) {
            $lewat = Time::parse($value->format('Y-m-d'))->isAfter(Time::today());

            $absenByTanggal = $this->presensiSiswaModel
               ->getPresensiByKelasTanggal($idKelas, $value->format('Y-m-d'));

            $absenByTanggal['lewat'] = $lewat;

            array_push($dataAbsen, $absenByTanggal);
            array_push($arrayTanggal, $value);
         }
      }

      $laki = 0;

      foreach ($siswa as $value) {
         if ($value['jenis_kelamin'] != 'Perempuan') {
            $laki++;
         }
      }

      $data = [
         'tanggal' => $arrayTanggal,
         'bulan' => $begin->format('F'),
         'listAbsen' => $dataAbsen,
         'listSiswa' => $siswa,
         'jumlahSiswa' => [
            'laki' => $laki,
            'perempuan' => count($siswa) - $laki
         ],
         'kelas' => $kelas,
         'grup' => "kelas " . $kelas['kelas'] . " " . $kelas['jurusan']
      ];

      return view('admin/generate-laporan/laporan-siswa', $data);
   }

   public function generateLaporanGuru()
   {
      $guru = $this->guruModel->getAllGuru();

      if (empty($guru)) {
         session()->setFlashdata([
            'msg' => 'Data guru kosong!',
            'error' => true
         ]);
         return redirect()->to('/admin/laporan');
      }

      $bulan = $this->request->getVar('tanggalGuru');

      // hari pertama dalam 1 bulan
      $begin = new DateTime($bulan);
      // tanggal terakhir dalam 1 bulan
      $end = (new DateTime($begin->format('Y-m-t')))->modify('+1 day');
      // interval 1 hari
      $interval = DateInterval::createFromDateString('1 day');
      // buat array dari semua hari di bulan
      $period = new DatePeriod($begin, $interval, $end);

      $arrayTanggal = [];
      $dataAbsen = [];

      foreach ($period as $value) {
         // kecualikan hari sabtu dan minggu
         if (!($value->format('D') == 'Sat' || $value->format('D') == 'Sun')) {
            $lewat = Time::parse($value->format('Y-m-d'))->isAfter(Time::today());

            $absenByTanggal = $this->presensiGuruModel
               ->getPresensiByTanggal($value->format('Y-m-d'));

            $absenByTanggal['lewat'] = $lewat;

            array_push($dataAbsen, $absenByTanggal);
            array_push($arrayTanggal, $value);
         }
      }

      $laki = 0;

      foreach ($guru as $value) {
         if ($value['jenis_kelamin'] != 'Perempuan') {
            $laki++;
         }
      }

      $data = [
         'tanggal' => $arrayTanggal,
         'bulan' => $begin->format('F'),
         'listAbsen' => $dataAbsen,
         'listGuru' => $guru,
         'jumlahGuru' => [
            'laki' => $laki,
            'perempuan' => count($guru) - $laki
         ],
         'grup' => 'guru'
      ];

      return view('admin/generate-laporan/laporan-guru', $data);
   }
}
