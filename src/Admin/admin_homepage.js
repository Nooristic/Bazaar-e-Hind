const tents = [
  {
    key: "user-verification",
    label: "User Verification",
    desc: "Review & approve pending user documents",
    img: "../../assets/tent-catalog.jpg",
    icon: "✅",
    link: "verify.php"
  },
  {
    key: "verification-queue",
    label: "Verification Queue & Disputes",
    desc: "Pending verifications + basic dispute overview",
    img: "../../assests/tent-orders.jpg",
   icon: "📋",
    link: "queue.php"
  },
  {
   key: "dispute-resolution",
    label: "Dispute Resolution",
    desc: "Manage & resolve buyer-seller disputes",
    img: "../../assests/tent-chat.jpg",
   icon: "⚖️",
    link: "disputes.php"
  },
  {
   key: "content-moderation",
    label: "Content Moderation",
    desc: "Moderate fabrics, forum posts & content",
    img: "../../assests/tent-samples.jpg",
   icon: "🛡️",
    link: "content_moderation.php"
  },
  {
   key: "listings-moderation",
    label: "Listings & Forum Moderation",
    desc: "Approve pending fabrics & review flagged posts",
    img: "../../assests/tent-exclusivity.jpg",
    icon: "🧵",
    link: "listings.php"
  },
  {
   key: "analytics-logs",
    label: "Analytics & Audit Logs",
    desc: "Platform stats, reports & admin activity logs",
    img: "../../assests/tent-forum.jpg",
   icon: "📈",
    link: "analytics_logs.php"
  },
  {
    key: "settings",
    label: "Settings",
    desc: "Configure your preferences",
    img: "../../assests/tent-settings.jpg",
    icon: "⚙️",
    link: "setting_manufacturer.php"
  }
];


function createTent(tent) {
  const card = document.createElement('div');
  card.className = 'tent-card';
  card.tabIndex = 0;

  // Background image
  const bg = document.createElement('img');
  bg.className = 'tent-bg';
  bg.src = tent.img;
  bg.alt = tent.label;
  card.appendChild(bg);

  // Overlay
  const overlay = document.createElement('div');
  overlay.className = 'tent-overlay';
  card.appendChild(overlay);

  // Content
  const content = document.createElement('div');
  content.className = 'tent-content';

  // Icon
  const icon = document.createElement('span');
  icon.className = 'tent-icon';
  icon.textContent = tent.icon;
  content.appendChild(icon);

  // Title
  const title = document.createElement('div');
  title.className = 'tent-title';
  title.textContent = tent.label;
  content.appendChild(title);

  // Description
  const desc = document.createElement('div');
  desc.className = 'tent-desc';
  desc.textContent = tent.desc;
  content.appendChild(desc);

  card.appendChild(content);

  // Click handler
  card.addEventListener('click', function (e) {
    e.preventDefault();
    if (confirm(`Go to ${tent.label}?`)) {
      window.location.href = tent.link;
    }
  });
  card.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      card.click();
    }
  });

  return card;
}

document.addEventListener('DOMContentLoaded', function() {
  const grid = document.querySelector('.tent-grid');
  tents.forEach(tent => {
    grid.appendChild(createTent(tent));
  });
});
