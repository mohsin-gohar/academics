/**
 * EduSphere - AJAX Student Task Manager
 * Vanilla JavaScript & Fetch API implementation
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('tasksTableBody');
    if (!tableBody) return;

    const searchInput = document.getElementById('taskSearchInput');
    const statusSelect = document.getElementById('taskStatusSelect');
    const prioritySelect = document.getElementById('taskPrioritySelect');
    const studentSelect = document.getElementById('taskStudentSelect');
    const clearBtn = document.getElementById('taskClearFilters');
    const paginationNav = document.getElementById('taskPaginationNav');
    const statTotal = document.getElementById('statTotalTasks');
    const statPending = document.getElementById('statPendingTasks');
    const statInProgress = document.getElementById('statInProgressTasks');
    const statCompleted = document.getElementById('statCompletedTasks');
    const statOverdue = document.getElementById('statOverdueTasks');

    let currentPage = 1;
    let debounceTimer = null;

    // Base path helper
    const metaCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Fetch tasks with query parameters
    async function loadTasks(page = 1) {
        currentPage = page;
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                    <span class="ms-2 text-muted">Loading tasks...</span>
                </td>
            </tr>
        `;

        const params = new URLSearchParams({
            page: currentPage,
            search: searchInput ? searchInput.value.trim() : '',
            status: statusSelect ? statusSelect.value : '',
            priority: prioritySelect ? prioritySelect.value : '',
            student: studentSelect ? studentSelect.value : ''
        });

        // Determine correct relative URL to ajax endpoint
        const currentPath = window.location.pathname;
        const ajaxUrl = currentPath.includes('/tasks/') ? '../ajax/fetch_tasks.php' : 'ajax/fetch_tasks.php';

        try {
            const res = await fetch(`${ajaxUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) {
                throw new Error(`Server returned HTTP ${res.status}`);
            }

            const data = await res.json();
            if (data.success) {
                renderTable(data.tasks);
                renderPagination(data.pagination);
                updateStats(data.stats);
            } else {
                showEmptyOrError(data.message || 'Unable to retrieve tasks');
            }
        } catch (err) {
            console.error('Task fetch error:', err);
            showEmptyOrError('Network error while loading tasks. Please try again.');
        }
    }

    function showEmptyOrError(msg) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary opacity-50"></i>
                    <p class="mb-0">${escapeHtml(msg)}</p>
                </td>
            </tr>
        `;
        if (paginationNav) paginationNav.innerHTML = '';
    }

    function renderTable(tasks) {
        if (!tasks || tasks.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-tasks fa-3x mb-3 text-secondary opacity-50"></i>
                        <h5>No tasks found</h5>
                        <p class="text-muted small">Try adjusting your search criteria or create a new task.</p>
                    </td>
                </tr>
            `;
            return;
        }

        const isInsideTasksDir = window.location.pathname.includes('/tasks/');
        const linkPrefix = isInsideTasksDir ? '' : 'tasks/';

        let html = '';
        tasks.forEach(t => {
            const overdueHtml = t.is_overdue ? '<span class="badge bg-danger ms-1">Overdue</span>' : '';
            
            // Inline status selector or badge
            let statusCellHtml = '';
            if (t.can_change_status) {
                statusCellHtml = `
                    <select class="form-select form-select-sm task-status-select" data-task-id="${t.id}" style="width: auto; min-width: 120px;">
                        <option value="Pending" ${t.status === 'Pending' ? 'selected' : ''}>Pending</option>
                        <option value="In Progress" ${t.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                        <option value="Completed" ${t.status === 'Completed' ? 'selected' : ''}>Completed</option>
                        <option value="Cancelled" ${t.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                    </select>
                `;
            } else {
                statusCellHtml = `<span class="badge ${t.status_badge}">${escapeHtml(t.status)}</span>`;
            }

            // Action buttons
            let actionsHtml = `<a href="${linkPrefix}show.php?id=${t.id}" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>`;
            if (t.can_edit) {
                actionsHtml += ` <a href="${linkPrefix}edit.php?id=${t.id}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>`;
            }
            if (t.can_delete) {
                actionsHtml += ` <a href="${linkPrefix}delete.php?id=${t.id}&csrf_token=${metaCsrf}" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this task?');"><i class="fas fa-trash"></i></a>`;
            }

            html += `
                <tr id="task-row-${t.id}">
                    <td>
                        <a href="${linkPrefix}show.php?id=${t.id}" class="text-decoration-none text-dark fw-medium">
                            ${escapeHtml(t.title)}
                        </a>
                        ${t.description ? `<br><small class="text-muted">${escapeHtml(t.description.substring(0, 60))}${t.description.length > 60 ? '...' : ''}</small>` : ''}
                    </td>
                    <td>${escapeHtml(t.student_name)}</td>
                    <td>${escapeHtml(t.course_name)}</td>
                    <td><span class="badge ${t.priority_badge}">${escapeHtml(t.priority)}</span></td>
                    <td id="task-status-cell-${t.id}">${statusCellHtml}</td>
                    <td>${escapeHtml(t.due_date)} ${overdueHtml}</td>
                    <td class="text-end text-nowrap">${actionsHtml}</td>
                </tr>
            `;
        });

        tableBody.innerHTML = html;
        attachInlineStatusEvents();
    }

    function attachInlineStatusEvents() {
        document.querySelectorAll('.task-status-select').forEach(sel => {
            sel.addEventListener('change', async function() {
                const taskId = this.getAttribute('data-task-id');
                const newStatus = this.value;
                const originalValue = this.getAttribute('data-prev-val') || this.value;
                this.disabled = true;

                const currentPath = window.location.pathname;
                const ajaxUrl = currentPath.includes('/tasks/') ? '../ajax/update_status.php' : 'ajax/update_status.php';

                try {
                    const res = await fetch(ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': metaCsrf
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            status: newStatus,
                            csrf_token: metaCsrf
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        this.setAttribute('data-prev-val', newStatus);
                        if (typeof window.showToast === 'function') {
                            window.showToast(data.message, 'success');
                        }
                        // Refresh active stats
                        refreshStatsOnly();
                    } else {
                        this.value = originalValue;
                        if (typeof window.showToast === 'function') {
                            window.showToast(data.message || 'Status update failed', 'danger');
                        }
                    }
                } catch (e) {
                    console.error('Update error:', e);
                    this.value = originalValue;
                    if (typeof window.showToast === 'function') {
                        window.showToast('Network error updating task', 'danger');
                    }
                } finally {
                    this.disabled = false;
                }
            });
        });
    }

    async function refreshStatsOnly() {
        const currentPath = window.location.pathname;
        const ajaxUrl = currentPath.includes('/tasks/') ? '../ajax/fetch_tasks.php' : 'ajax/fetch_tasks.php';
        try {
            const res = await fetch(`${ajaxUrl}?per_page=1`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.success && data.stats) {
                updateStats(data.stats);
            }
        } catch (e) {}
    }

    function updateStats(stats) {
        if (!stats) return;
        if (statTotal) statTotal.textContent = stats.total;
        if (statPending) statPending.textContent = stats.pending;
        if (statInProgress) statInProgress.textContent = stats.in_progress;
        if (statCompleted) statCompleted.textContent = stats.completed;
        if (statOverdue) statOverdue.textContent = stats.overdue;
    }

    function renderPagination(p) {
        if (!paginationNav) return;
        if (!p || p.total_pages <= 1) {
            paginationNav.innerHTML = '';
            return;
        }

        let pagesHtml = '<ul class="pagination pagination-sm mb-0 justify-content-center">';
        if (p.has_prev) {
            pagesHtml += `<li class="page-item"><button class="page-link" data-page="${p.current_page - 1}">Previous</button></li>`;
        } else {
            pagesHtml += `<li class="page-item disabled"><span class="page-link">Previous</span></li>`;
        }

        for (let i = 1; i <= p.total_pages; i++) {
            if (i === p.current_page) {
                pagesHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                pagesHtml += `<li class="page-item"><button class="page-link" data-page="${i}">${i}</button></li>`;
            }
        }

        if (p.has_next) {
            pagesHtml += `<li class="page-item"><button class="page-link" data-page="${p.current_page + 1}">Next</button></li>`;
        } else {
            pagesHtml += `<li class="page-item disabled"><span class="page-link">Next</span></li>`;
        }
        pagesHtml += '</ul>';

        paginationNav.innerHTML = pagesHtml;

        paginationNav.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetPage = parseInt(btn.getAttribute('data-page'), 10);
                loadTasks(targetPage);
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Debounced search
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                loadTasks(1);
            }, 300);
        });
    }

    // Dropdown filters
    [statusSelect, prioritySelect, studentSelect].forEach(el => {
        if (el) {
            el.addEventListener('change', () => loadTasks(1));
        }
    });

    // Clear filters
    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (searchInput) searchInput.value = '';
            if (statusSelect) statusSelect.value = '';
            if (prioritySelect) prioritySelect.value = '';
            if (studentSelect) studentSelect.value = '';
            loadTasks(1);
        });
    }

    // Initial AJAX fetch
    loadTasks(1);
});
