<?php
  require __DIR__ . '/../bootstrap.php';

  $bootstrapData = bootstrapAccounts([
    'require_login' => false,
  ]);

  extract($bootstrapData, EXTR_OVERWRITE);
?>

<?php // Headers 
  $page_title = "Maintenance | careerinstitute.co.in";
  
  require_once '../components/header.php'; 
?>

<section class="section-border border-primary">
  <div class="container d-flex flex-column">
    <div class="row align-items-center justify-content-center gx-0 min-vh-100">
      <div class="d-flex flex-column align-items-center justify-content-center col-md-8 px-8 px-md-8 py-8 py-md-8">
        <svg height="150" width="150" fill="#000000" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" enable-background="new 0 0 100 100" xml:space="preserve">
          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
          <g id="SVGRepo_iconCarrier"> 
            <path d="M46.5,30.5c0.2,0.5,0.6,0.8,1.1,0.8h27.5c1.3,0,2.7,0.3,3.9,0.8c0.5,0.3,1.1-0.1,1.1-0.8 c0.1-2.8-2.1-5-4.9-5.1c0,0-0.1,0-0.1,0H45c-0.3,0-0.6,0.2-0.6,0.5c0,0.1,0,0.3,0.1,0.4L46.5,30.5z"></path> 
            <path d="M75.1,36.4H46.7c-1.8,0-3.4-1-4.4-2.5l-4.4-7.6c-0.8-1.6-2.5-2.6-4.3-2.6h-8.5c-2.8,0-5,2.2-5,5 c0,0,0,0.1,0,0.1v45.8c-0.1,2.8,2.1,5,4.9,5.1c0,0,0.1,0,0.1,0h50c2.8,0,5-2.2,5-5c0,0,0-0.1,0-0.1V41.5c0.1-2.8-2.1-5-4.9-5.1 C75.2,36.4,75.1,36.4,75.1,36.4z M62,69.7c-1.1,1.2-2.9,1.2-4.1,0.1c0,0-0.1-0.1-0.1-0.1l-9.9-9.9c-0.6,0.3-1.3,0.4-2,0.5 c-4.2,0.5-8-2.6-8.5-6.8c0-0.3,0-0.6,0-0.8c0-0.7,0.1-1.5,0.3-2.2c0.1-0.2,0.3-0.4,0.6-0.3c0.1,0,0.1,0,0.1,0.1l4.3,4.3 c0.3,0.3,0.9,0.3,1.2,0l3-3c0.3-0.3,0.3-0.9,0-1.2l-4.3-4.3c-0.2-0.2-0.1-0.5,0.1-0.6c0,0,0.1-0.1,0.1-0.1c0.7-0.2,1.5-0.3,2.2-0.3 c4.2,0,7.6,3.4,7.6,7.7c0,0.3,0,0.6,0,0.8c-0.1,0.7-0.3,1.3-0.5,2l9.9,9.9c1.3,1.1,1.5,2.9,0.4,4.2C62.2,69.7,62.2,69.8,62,69.7z"></path> 
          </g>
        </svg>

        <h1 class="mb-0 fw-bold text-center">
          Maintenance Mode
        </h1>

        <p class="lead mb-0 mt-6 text-center text-body-secondary">
          Developers are working to make the services better. Please wait for a moment before trying again.
        </p>

      </div>
    </div>
  </div>
</section>

<?php require_once '../components/footer.php'; ?>
