const tents = [
  {
    key: "product",
    label: "Product Management",
    desc: "Browse catalog, manage inventory & fabrics",
    img: "../../assests/tent-catalog.jpg",
    icon: "📦",
    link: "prod_manufacturer.php"
  },
  {
    key: "order",
    label: "Orders",
    desc: "Track and manage your orders",
    img: "../../assests/tent-orders.jpg",
    icon: "🛒",
    link: "order_manufacturer.html"
  },
  {
    key: "chat",
    label: "Chat & Call",
    desc: "Connect with buyers and suppliers",
    img: "../../assests/tent-chat.jpg",
    icon: "💬",
    link: "chat_manufacturer.html"
  },
  {
    key: "sample",
    label: "Sample Management",
    desc: "Request and manage product samples",
    img: "../../assests/tent-samples.jpg",
    icon: "📑",
    link: "sample_manufacturer.html"
  },
  {
    key: "exclusive",
    label: "Exclusivity Agreements",
    desc: "Manage exclusive partnerships",
    img: "../../assests/tent-exclusivity.jpg",
    icon: "📝",
    link: "ex_agreements_manufacturer.html"
  },
  {
    key: "community",
    label: "Community Forum",
    desc: "Join discussions and share insights",
    img: "../../assests/tent-forum.jpg",
    icon: "👥",
    link: "community_manufacturer.html"
  },
  {
    key: "settings",
    label: "Settings",
    desc: "Configure your preferences",
    img: "../../assests/tent-settings.jpg",
    icon: "⚙️",
    link: "/settings"
  },
  {
    key: "payment",
    label: "Payment Management",
    desc: "Manage payments and transactions",
    img: "../../assests/tent-orders.jpg",
    icon: "💳",
    link: "payment_manufacturer.html"
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
