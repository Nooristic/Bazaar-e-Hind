const tents = [
  {
    key: "browse",
    label: "Browse Fabrics",
    desc: "Discover premium & exclusive collections",
    img: "../../assests/tent-browse-fabrics.jpg",
    icon: "🧵",
    link: "browse_fabrics.php"
  },
  {
    key: "orders",
    label: "My Orders",
    desc: "Track pending, processing & completed orders",
    img: "../../assests/tent-orders.jpg",
    icon: "📦",
    link: "orders_wholesaler.php"
  },
  {
    key: "chat",
    label: "Chat & Call",
    desc: "Negotiate & connect with manufacturers",
    img: "../../assests/tent-chat.jpg",
    icon: "💬",
    link: "chat_wholesaler.html"
  },
  {
    key: "samples",
    label: "Sample Requests",
    desc: "Request & manage fabric samples",
    img: "../../assests/tent-samples.jpg",
    icon: "📋",
    link: "samples_wholesaler.php"
  },
  {
    key: "exclusive",
    label: "Exclusivity Agreements",
    desc: "View & request exclusive fabric access",
    img: "../../assests/tent-exclusivity.jpg",
    icon: "🤝",
    link: "exclusivity_agreement.php"
  },
  {
    key: "payments",
    label: "Payments & Invoices",
    desc: "Manage payments, receipts & dues",
    img: "../../assests/tent-payments.jpg",
    icon: "₹",
    link: "payments_wholesaler.php"
  },
  {
    key: "forum",
    label: "Community Forum",
    desc: "Discuss trends & textile industry updates",
    img: "../../assests/tent-forum.jpg",
    icon: "🌐",
    link: "forum_wholesaler.php"
  },
  {
    key: "profile",
    label: "My Profile & Settings",
    desc: "Update account, GST & preferences",
    img: "../../assests/tent-settings.jpg",
    icon: "⚙️",
    link: "profile_wholesaler.php"
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
  bg.loading = "lazy"; // Better performance
  card.appendChild(bg);

  // Overlay gradient
  const overlay = document.createElement('div');
  overlay.className = 'tent-overlay';
  card.appendChild(overlay);

  // Content container
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

  // Navigation with confirmation (same as original)
  card.addEventListener('click', function (e) {
    e.preventDefault();
    if (confirm(`Go to ${tent.label}?`)) {
      window.location.href = tent.link;
    }
  });

  // Keyboard accessibility
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
  if (grid) {
    tents.forEach(tent => {
      grid.appendChild(createTent(tent));
    });
  } else {
    console.warn("Tent grid container (.tent-grid) not found in the document");
  }
});