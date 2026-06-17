/* Phoenix — global client helpers loaded on every page (assets/app.js).
 * Page- and feature-specific behaviour lives in its own file; this holds only
 * what every page needs: the theme toggle, a generic confirm-on-submit guard,
 * and the Lucide icon render. */

// Theme toggle (the init snippet runs inline in <head> to avoid a flash).
function phToggleTheme() {
  var dark = document.documentElement.classList.toggle("theme-dark")
  document.documentElement.classList.toggle("theme-light", !dark)
  try {
    localStorage.setItem("phoenix-theme", dark ? "dark" : "light")
  } catch {
    /* ignore */
  }
}

// (Re)render Lucide icons for any <span class="ph-ico" data-lucide="…">.
function phInitIcons() {
  if (window.lucide) lucide.createIcons({ attrs: { class: "ph-ico" } })
}

// Wire the theme toggle(s), a generic data-confirm submit guard, then paint
// icons. Scripts load at the end of <body>, so the DOM is ready here.
document.querySelectorAll(".ph-theme-toggle").forEach(function (btn) {
  btn.addEventListener("click", phToggleTheme)
})

document.addEventListener("submit", function (e) {
  var form = e.target
  var msg = form.getAttribute && form.getAttribute("data-confirm")
  if (msg && !window.confirm(msg)) e.preventDefault()
})

phInitIcons()
