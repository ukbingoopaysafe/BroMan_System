// Minimal client-side search / date filtering and details preview
document.addEventListener('DOMContentLoaded', function(){
  const table = document.getElementById('activityTable');
  if(!table) return;

  const rows = Array.from(table.tBodies[0].rows);
  // Search and date filters were removed from the UI; related client-side hooks removed as well.

  // helper: decode UTF-8 from base64
  function b64DecodeUnicode(str) {
    // from: https://stackoverflow.com/a/30106551
    return decodeURIComponent(Array.prototype.map.call(atob(str), function(c) {
      return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
  }

  // Expand/collapse employee rows
  table.addEventListener('click', function(e){
    const exp = e.target.closest('.expand-toggle');
    if(exp){
      const emp = exp.dataset.employeeId;
      const subrows = table.querySelectorAll('tr.subrow[data-parent="'+emp+'"]');
      const open = exp.classList.toggle('expanded');
      subrows.forEach(r => r.style.display = open ? '' : 'none');
      return;
    }

    // details toggle: open modal and show decoded content
    const btn = e.target.closest('.details-toggle');
    if(!btn) return;
    const encoded = btn.dataset.details || '';
    try{
      const txt = b64DecodeUnicode(encoded);
      const modal = document.getElementById('detailsModal');
      const modalBody = modal.querySelector('.modal-body');
      modalBody.innerText = txt;
      modal.classList.add('open');
    }catch(err){ alert('Details unavailable'); }
  });

  // modal close handlers
  const modal = document.getElementById('detailsModal');
  if(modal){
    modal.querySelector('.modal-close').addEventListener('click', function(){ modal.classList.remove('open'); });
    modal.addEventListener('click', function(e){ if(e.target === modal) modal.classList.remove('open'); });
  }
});
