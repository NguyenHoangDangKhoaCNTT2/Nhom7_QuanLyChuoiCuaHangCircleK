/* File dùng chung — không sửa riêng lẻ. */
(function () {
  const sidebar = document.getElementById("sidebar");
  const wrapper = document.getElementById("content-wrapper");
  const backdrop = document.getElementById("backdrop");
  const toggle = document.getElementById("sidebarCollapse");
  const isMobile = () => window.innerWidth < 992;

  if (toggle && sidebar) {
    toggle.addEventListener("click", () => {
      if (isMobile()) {
        sidebar.classList.toggle("open");
        backdrop.classList.toggle("show");
      } else {
        sidebar.classList.toggle("collapsed");
        wrapper.classList.toggle("expanded");
        // báo cho Chart.js vẽ lại theo bề rộng mới
        setTimeout(() => window.dispatchEvent(new Event("resize")), 360);
      }
    });
  }
  if (backdrop) {
    backdrop.addEventListener("click", () => {
      sidebar.classList.remove("open");
      backdrop.classList.remove("show");
    });
  }

  const clock = document.getElementById("liveClock");
  if (clock) {
    setInterval(() => {
      clock.textContent = new Date().toLocaleTimeString("vi-VN");
    }, 1000);
  }

  document.addEventListener("keydown", (ev) => {
    if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === "k") {
      ev.preventDefault();
      const input = document.getElementById("quickSearch");
      if (input) input.focus();
    }
  });

  document.querySelectorAll(".segmented-control .seg-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      this.parentElement
        .querySelectorAll(".seg-btn")
        .forEach((b) => b.classList.remove("active"));
      this.classList.add("active");
    });
  });

  if (sidebar) {
    const savedScroll = localStorage.getItem("ck_sidebar_scroll");
    if (savedScroll) sidebar.scrollTop = parseInt(savedScroll, 10);
    sidebar.addEventListener("scroll", () => {
      localStorage.setItem("ck_sidebar_scroll", sidebar.scrollTop);
    });
  }
})();
