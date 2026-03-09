<?php
/** @var callable $__t */
/** @var callable $url */
?>

<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6">
        <h3 class="mb-0">Admin Kullanım Kılavuzu</h3>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="<?= $url('/admin') ?>">Admin</a></li>
          <li class="breadcrumb-item active" aria-current="page">Tutorial</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <!-- Dashboard & Giriş -->
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title">Genel Bakış</h5>
          </div>
          <div class="card-body">
            <p>NovelMangaReader Admin Paneli, sitenin tüm içeriklerini, kullanıcılarını ve sistem operasyonlarını yönetebileceğiniz merkezi bir yönetim merkezidir. Sol taraftaki menüyü kullanarak farklı modüller arasında geçiş yapabilirsiniz.</p>
            <ul>
              <li><strong>Dashboard:</strong> Sistemin anlık durumunu ve temel istatistikleri (aktif kullanıcılar, toplam içerik vb.) hızlıca görebileceğiniz alandır.</li>
              <li><strong>Analytics:</strong> Ziyaretçi sayıları, popüler türler ve kullanıcı etkileşimleri gibi detaylı verileri grafiklerle inceleyebilirsiniz.</li>
            </ul>
          </div>
        </div>

        <!-- İçerik Yönetimi -->
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title">İçerik ve Bölüm Yönetimi</h5>
          </div>
          <div class="card-body">
            <p>Bu bölüm, sitenin ana materyali olan Novel, Manga, Manhua ve Manhwa gibi serilerin yönetildiği yerdir.</p>
            <h6>Seri İşlemleri:</h6>
            <ul>
              <li>Yeni seri ekleme, mevcut seriyi düzenleme ve silme işlemleri buradan yapılır.</li>
              <li>Her serinin türü (Manga/Novel), durumu (Devam Ediyor/Tamamlandı) ve kapak resmi gibi detayları yönetilir.</li>
            </ul>
            <h6>Bölüm İşlemleri:</h6>
            <ul>
              <li>Serilerin içine bölümler (Chapter) eklenir. Novel bölümleri için metin, Manga bölümleri için resim URL'leri kullanılır.</li>
              <li>Bölüm numaralandırması otomatik veya manuel olarak ayarlanabilir.</li>
            </ul>
          </div>
        </div>

        <!-- Moderasyon -->
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title">Moderasyon (Blog ve Yorumlar)</h5>
          </div>
          <div class="card-body">
            <p>Kullanıcı etkileşimlerini kontrol altında tutmak için kullanılan araçlardır.</p>
            <ul>
              <li><strong>Blog Moderasyonu:</strong> Kullanıcıların paylaştığı blog yazılarını onaylayabilir, gizleyebilir veya silebilirsiniz. Sitede sadece onaylanan bloglar listelenir.</li>
              <li><strong>Yorum Yönetimi:</strong> İçerik ve bölümlerin altına yapılan yorumları denetleyebilir, topluluk kurallarına aykırı yorumları silebilirsiniz.</li>
            </ul>
          </div>
        </div>

        <!-- Kullanıcı ve Güvenlik -->
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title">Kullanıcılar ve RBAC</h5>
          </div>
          <div class="card-body">
            <p>Sistemi kullanan kişilerin ve yetkilerinin yönetildiği kritik bölümdür.</p>
            <ul>
              <li><strong>Kullanıcı Yönetimi:</strong> Kayıtlı kullanıcıların bilgilerini görebilir, rollerini değiştirebilir veya hesaplarını askıya alabilirsiniz.</li>
              <li><strong>RBAC (Rol Tabanlı Erişim Kontrolü):</strong> Hangi rolün (Admin, Moderatör, Editör vb.) hangi yetkilere (İçerik silme, Kullanıcı düzenleme vb.) sahip olacağını ince ince ayarlayabilirsiniz.</li>
            </ul>
          </div>
        </div>

        <!-- Sistem Operasyonları -->
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="card-title">Sistem İşlemleri ve Loglar</h5>
          </div>
          <div class="card-body">
            <p>Sistemin teknik sağlığı ve takibi için kullanılır.</p>
            <ul>
              <li><strong>System Ops:</strong> Önbelleği temizleme, bekleyen kuyruk işlerini (Queue) çalıştırma ve eski verileri (Retention) temizleme gibi bakım işlemleri yapılır.</li>
              <li><strong>Logs & Security:</strong> Giriş denemeleri, hata kayıtları (Error Logs) ve erişim kayıtları (Access Logs) buradan izlenir. Güvenlik ihlallerini tespit etmek için idealdir.</li>
            </ul>
          </div>
        </div>

        <!-- Önemli Not -->
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Unutmayın:</strong> Yaptığınız tüm işlemler sistem loglarına kaydedilmektedir. Kritik değişiklikler yapmadan önce dikkatli olunuz.
        </div>
      </div>
    </div>
  </div>
</div>
