/* Phoenix — click-to-copy info hashes (assets/hash.js).
 * Used on any page rendering the .hash component (view_hash_html): the index
 * and the admin Torrents table. The full hash rides on the button's data-hash;
 * the cell shows a truncated form. Falls back silently without a clipboard. */

function phCopyHash(btn) {
  var value = btn.getAttribute("data-hash") || ""
  var done = function () {
    btn.classList.add("is-copied")
    var ico = btn.querySelector(".ph-ico")
    if (ico) ico.setAttribute("data-lucide", "check")
    if (window.lucide) lucide.createIcons({ attrs: { class: "ph-ico" } })
    setTimeout(function () {
      btn.classList.remove("is-copied")
      if (ico) ico.setAttribute("data-lucide", "copy")
      if (window.lucide) lucide.createIcons({ attrs: { class: "ph-ico" } })
    }, 1200)
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(value).then(done, done)
  } else {
    done()
  }
}

document.querySelectorAll(".hash-copy").forEach(function (btn) {
  btn.addEventListener("click", function () {
    phCopyHash(btn)
  })
})
