/* Phoenix admin shell — loaded on every admin page (assets/admin.js).
 * Disables submit controls once any maintenance form (form.mysql) is submitted,
 * to prevent double-submission across the mutually exclusive setup/clean/optimize
 * forms. */

document.querySelectorAll("form.mysql").forEach(function (form) {
  form.addEventListener("submit", function () {
    document.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(function (b) {
      b.disabled = true
    })
  })
})
