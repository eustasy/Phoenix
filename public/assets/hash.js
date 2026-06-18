/* Phoenix — click-to-copy info hashes (assets/hash.js).
 * Used on any page rendering the .hash component (view_hash_html): the index
 * and the admin Torrents table. The full hash rides on the button's data-hash;
 * the cell shows a truncated form. Falls back silently without a clipboard. */

function phCopyHash(btn) {
  var value = btn.getAttribute("data-hash") || ""
  // Re-query the icon on every swap: lucide.createIcons() REPLACES the node, so
  // a reference captured earlier goes stale and the revert would no-op.
  var setIcon = function (name) {
    var ico = btn.querySelector(".ph-ico")
    if (ico) ico.setAttribute("data-lucide", name)
    if (window.lucide) lucide.createIcons({ attrs: { class: "ph-ico" } })
  }
  var done = function () {
    btn.classList.add("is-copied")
    setIcon("check")
    // Revert to the copy icon so the button reads as ready to use again. Clear
    // any pending revert first, so rapid re-clicks don't flip it mid-confirm.
    if (btn._revert) clearTimeout(btn._revert)
    btn._revert = setTimeout(function () {
      btn.classList.remove("is-copied")
      setIcon("copy")
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
