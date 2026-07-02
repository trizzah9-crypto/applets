<?php
require("header.php");

require_once("db.php"); // your MySQLi connection

$expiryDateText = "N/A";

// Logged-in user id
$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    try {
        $stmt = $conn->prepare("
            SELECT subscription_expires_at 
            FROM businesses 
            WHERE owner_user_id = :userId
            LIMIT 1
        ");
        $stmt->execute(['userId' => $userId]);
        $subscription_expires_at = $stmt->fetchColumn();

        if (!empty($subscription_expires_at)) {
            $expiryDateText = date("F j, Y", strtotime($subscription_expires_at));
        }
    } catch (PDOException $e) {
        // Optionally log the error or handle it
        $expiryDateText = "N/A";
    }
}

// Subscription logic
$success = false;
$error = '';
$submittedPlan = '';
$submittedPhone = '';
$expiryDate = '';

$plans = [
    'yearly' => ['label' => 'Yearly Plan', 'price' => 3000, 'interval' => 'year', 'days' => 365],
    'monthly' => ['label' => 'Monthly Plan', 'price' => 300, 'interval' => 'month', 'days' => 30],
    'weekly' => ['label' => 'Weekly Plan', 'price' => 80, 'interval' => 'week', 'days' => 7],
];

?>


<style>
  /* Page container */
  .plans-wrapper {
    max-width: 900px;
    margin: 1.5rem auto 3rem;
    padding: 0 1rem;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #222;
  }

  .page-title {
    text-align: center;
    font-weight: 700;
    margin-bottom: 1rem;
    color: rgba(5, 73, 96, 0.9);
  }

  /* Alerts */
  .alert {
    padding: 1rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    font-weight: 600;
    text-align: center;
  }
  .success-alert {
    background-color: #dff0d8;
    border: 1.5px solid #d6e9c6;
    color: #3c763d;
  }
  .error-alert {
    background-color: #f2dede;
    border: 1.5px solid #ebccd1;
    color: #a94442;
  }

  .expiry-badge {
    display: inline-block;
    background: rgba(5, 73, 96, 0.9);
    color: white;
    font-size: 0.9rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    margin-top: 0.7rem;
    font-weight: 600;
  }

  /* Plans list */
  .plans-list {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
  }

  .plan-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.8rem 1.5rem 1.3rem;
    min-width: 260px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    cursor: pointer;
    user-select: none;
    border: 2px solid transparent;
    transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.25s ease;
  }
  .plan-card:hover,
  .plan-card:focus {
    border-color: rgba(5, 73, 96, 0.9);
    box-shadow: 0 8px 22px rgba(0,123,255,0.25);
    transform: translateY(-6px);
    outline: none;
  }

  .plan-header h3 {
    color: #ff7900;
    margin-bottom: 0.3rem;
    font-weight: 700;
    font-size: 1.3rem;
  }

  .plan-price {
    font-size: 2.1rem;
    font-weight: 800;
    margin-bottom: 0.15rem;
    color: rgba(5, 73, 96, 0.9);
  }

  .plan-interval {
    font-size: 0.95rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #555;
    margin-bottom: 1rem;
  }

  .plan-description {
    font-size: 0.95rem;
    color: #555;
    line-height: 1.3;
  }

  /* Modal styles */
  .modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    padding: 1rem;
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  .modal.active {
    visibility: visible;
    opacity: 1;
  }
  .modal-content {
    background: white;
    border-radius: 12px;
    padding: 2rem 2.5rem;
    max-width: 380px;
    width: 100%;
    box-shadow: 0 12px 28px rgba(0,0,0,0.18);
    position: relative;
  }

  .modal-close {
    position: absolute;
    top: 14px;
    right: 16px;
    background: none;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: #555;
    transition: color 0.25s ease;
  }
  .modal-close:hover {
    color: rgba(5, 73, 96, 0.9);;
  }

  .modal h2 {
    margin-bottom: 1.3rem;
    font-weight: 700;
    color: #ff7900;
    text-align: center;
  }

  .modal label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #444;
  }

  .modal input[type="text"] {
    width: 100%;
    padding: 0.6rem 0.75rem;
    font-size: 1rem;
    border: 1.8px solid #ccc;
    border-radius: 8px;
    box-sizing: border-box;
    transition: border-color 0.3s ease;
  }
  .modal input[type="text"]:focus {
    outline: none;
    border-color: rgba(5, 73, 96, 0.9);;
    box-shadow: 0 0 6px rgba(29, 136, 250, 0.3);
  }

  .btn-pay {
    margin-top: 1.4rem;
    width: 100%;
    padding: 0.75rem;
    background-color: rgba(5, 73, 96, 0.9);
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.25s ease;
  }
  .btn-pay:hover {
    background-color: rgba(5, 73, 96, 0.9);
  }

  .plans-list {
  display: flex;
  justify-content: center;
  gap: 1.5rem;       /* space between cards */
  flex-wrap: nowrap; /* keep all plans in one line */
  margin-top: 1rem;
}

.plan-card {
  flex: 1 1 260px;   /* allow shrinking and growing but start at 260px */
  max-width: 380px;  /* limit max width */
  background: #fff;
  border-radius: 14px;
  padding: 1.8rem 1.5rem 1.3rem;
  box-shadow: 0 4px 14px rgba(0,0,0,0.08);
  cursor: pointer;
  user-select: none;
  border: 2px solid transparent;
  transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.25s ease;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: center;
}

@media (max-width: 900px) {
  .plans-list {
    flex-wrap: wrap;  /* allow wrapping on smaller screens */
  }
  .plan-card {
    max-width: 100%;
    flex: 1 1 100%;
    margin-bottom: 1rem;
  }
}


  /* Responsive */
  @media (max-width: 640px) {
    .plans-list {
      flex-direction: column;
      align-items: center;
    }
    .plan-card {
      min-width: 90%;
    }
  }
</style>

<div style="
    position: fixed;
    top: 10px;
    right: 200px;
    background: rgba(5, 73, 96, 0.9);;
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    z-index: 9999;
">
    Expires on <?= htmlspecialchars($expiryDateText) ?>
</div>


<div id="expiryBadge" style="
  position: fixed;
  top: 10px;
  right: 10px;
  background: #007BFF;
  color: white;
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.9rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  z-index: 10000;
  display: none; /* hide initially */
">
  Expires on <span id="expiryDateText"></span>
</div>

<div class="p" role="main">
   <h2 class="text-center mb-4 py-3 px-4 rounded shadow-sm" 
        style="
            background: rgba(0, 123, 255, 0.1); 
            color: #004549ff; 
            font-weight: 600; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
            border-left: 5px solid #025659ff;
            display: inline-block;
            margin-top: 0px;
            margin-left: 50%;
            transform: translateX(-50%);
        ">
        Choose Your Subscription Plan
    </h2>

  <?php if ($success): ?>
    <div class="alert success-alert" role="alert" aria-live="polite">
      Thank you! Your subscription to the <strong><?= htmlspecialchars($plans[$submittedPlan]['label']) ?></strong> 
      (KES <?= number_format($plans[$submittedPlan]['price']) ?> per <?= $plans[$submittedPlan]['interval'] ?>) was successful (mocked).<br />
      <span class="expiry-badge">Expires on <?= htmlspecialchars($expiryDateText) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert error-alert" role="alert" aria-live="assertive"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="plans-list" role="list" aria-label="Subscription plans">
    <?php foreach ($plans as $key => $plan): ?>
      <div class="plan-card" role="listitem" tabindex="0" data-plan="<?= $key ?>" aria-label="<?= $plan['label'] ?> costing KES <?= number_format($plan['price']) ?> per <?= $plan['interval'] ?>">
        <div class="plan-header">
          <h3><?= $plan['label'] ?></h3>
          <div class="plan-price">KES <?= number_format($plan['price']) ?></div>
          <div class="plan-interval">per <?= $plan['interval'] ?></div>
        </div>
        <p class="plan-description">
          <?php
            if ($key === 'yearly') {
              echo 'Best value for long-term use. Save money by subscribing annually.';
            } elseif ($key === 'monthly') {
              echo 'Flexible monthly payments with easy cancellation.';
            } else {
              echo 'Short-term weekly access for quick needs.';
            }
          ?>
        </p>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Modal -->
  <div class="modal" id="planModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-content">
      <button type="button" class="modal-close" aria-label="Close modal">&times;</button>
      <h2 id="modalTitle">Subscribe</h2>
      <form id="subscribeForm" novalidate>

        <input type="hidden" name="plan" id="modalPlan" value="" />
        <label for="phone">Enter Phone Number</label>
        <input type="text" id="phone" name="phone" pattern="\d{9,12}" placeholder="e.g. 0712345678" required autocomplete="tel" />
        <button type="submit" class="btn-pay">Pay Now</button>
      </form>
    </div>
  </div>
</div>

<script>
  const planCards = document.querySelectorAll('.plan-card');
  const modal = document.getElementById('planModal');
  const modalPlanInput = document.getElementById('modalPlan');
  const modalTitle = document.getElementById('modalTitle');
  const modalCloseBtn = modal.querySelector('.modal-close');
  const phoneInput = document.getElementById('phone');

  planCards.forEach(card => {
    card.addEventListener('click', () => openModal(card));
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openModal(card);
      }
    });
  });

  function openModal(card) {
    const planKey = card.getAttribute('data-plan');
    const planLabel = card.querySelector('h3').textContent;
    modalPlanInput.value = planKey;
    modalTitle.textContent = `Subscribe to ${planLabel}`;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('active');
    phoneInput.value = '';
    phoneInput.focus();
  }

  function closeModal() {
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    phoneInput.value = '';
  }

  modalCloseBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', e => {
    if (e.target === modal) {
      closeModal();
    }
  });

  // Optional: Close modal on ESC key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeModal();
    }
  });
</script>

<script>
const subscribeForm = document.getElementById('subscribeForm');

subscribeForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(subscribeForm);

    fetch('ajax/subscribe.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {

          closeModal();
          document.querySelectorAll('.alert').forEach(a => a.remove());

          if (data.status === 'payment_required') {
              window.location.href = data.redirect_url;
              return;
          }

          if (data.status === 'success') {
              const successHTML = `
                  <div class="alert success-alert">
                      Thank you! Your subscription to <strong>${data.plan_label}</strong>
                      (KES ${data.price} per ${data.interval}) was successful.<br>
                      <span class="expiry-badge">Expires on ${data.expiry}</span>
                  </div>
              `;
              document.querySelector('.p').insertAdjacentHTML('afterbegin', successHTML);
          } else {
              const errorHTML = `
                  <div class="alert error-alert">
                      ${data.message}
                  </div>
              `;
              document.querySelector('.p').insertAdjacentHTML('afterbegin', errorHTML);
          }
      });

});
</script>


<?php require("foot.php"); ?>
