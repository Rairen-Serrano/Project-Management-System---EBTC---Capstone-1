// Task Management Functions
let projectId = null;
let selectedAssignees = new Set(); // Store selected assignee IDs
let availablePersonnel = []; // Store all available personnel

// Show Add Category Modal
function showAddCategoryModal() {
    const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Get project ID from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    projectId = urlParams.get('project_id');
    
    if (!projectId) {
        alert('Project ID is missing');
        window.location.href = 'projects.php';
        return;
    }

    // Add event listeners for modals
    document.getElementById('addCategoryBtn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
        modal.show();
    });

    document.getElementById('addTaskBtn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
        modal.show();
        loadProjectPersonnel(); // Load personnel when modal opens
    });

    // Add event listener for personnel list toggle
    const showAssigneeList = document.getElementById('showAssigneeList');
    const assigneeListContainer = document.getElementById('assigneeListContainer');

    showAssigneeList.addEventListener('click', function() {
        const isVisible = assigneeListContainer.style.display === 'block';
        assigneeListContainer.style.display = isVisible ? 'none' : 'block';
        if (!isVisible) {
            loadProjectPersonnel();
        }
    });

    // Load initial data
    loadCategories();
    loadTasks();
    loadTimeline();
    updateProgress();
    
    // Set minimum date for task due date
    const taskDueDate = document.getElementById('taskDueDate');
    if (taskDueDate) {
        const today = new Date().toISOString().split('T')[0];
        taskDueDate.min = today;
    }
});

// Load Categories
async function loadCategories() {
    try {
        const response = await fetch(`api/tasks.php?action=categories&project_id=${projectId}`);
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error || 'Failed to load categories');
        
        // Sort categories by category_id
        data.categories.sort((a, b) => a.category_id - b.category_id);
        
        // Update category list
        const categoryList = document.getElementById('categoryList');
        categoryList.innerHTML = '';
        
        data.categories.forEach(category => {
            const col = document.createElement('div');
            col.className = 'col-md-4 mb-3';
            col.innerHTML = `
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="card-title mb-0">${escapeHtml(category.category_name)}</h6>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteCategory(${category.category_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <p class="card-text small text-muted">
                            ${escapeHtml(category.description || 'No description')}
                        </p>
                    </div>
                </div>
            `;
            categoryList.appendChild(col);
        });

        // Update category select in task form
        const taskCategory = document.getElementById('taskCategory');
        taskCategory.innerHTML = '<option value="">Select Category</option>';
        data.categories.forEach(category => {
            taskCategory.innerHTML += `
                <option value="${category.category_id}">
                    ${escapeHtml(category.category_name)}
                </option>
            `;
        });
    } catch (error) {
        console.error('Error loading categories:', error);
        showAlert('error', 'Failed to load categories');
    }
}

// Load Tasks
async function loadTasks() {
    try {
        const response = await fetch(`api/tasks.php?action=tasks&project_id=${projectId}`);
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error || 'Failed to load tasks');
        
        const taskTable = document.querySelector('#taskTable tbody');
        taskTable.innerHTML = '';
        
        data.tasks.forEach(task => {
            const assigneesHtml = task.assignees.map(assignee => `
                <div class="mb-1">
                    <div class="fw-bold">${escapeHtml(assignee.name)}</div>
                    <small class="text-muted">${escapeHtml(assignee.email)}</small>
                </div>
            `).join('');

            const row = document.createElement('tr');
            row.innerHTML = `
                <td style="width: 20%">
                    <div class="fw-bold">${escapeHtml(task.task_name)}</div>
                    <div class="small text-muted">${escapeHtml(task.description || '')}</div>
                </td>
                <td style="width: 15%">${escapeHtml(task.category_name)}</td>
                <td style="width: 30%">
                    ${assigneesHtml || '<div class="text-muted">No assignees</div>'}
                </td>
                <td style="width: 15%">${formatDate(task.due_date)}</td>
                <td style="width: 10%">
                    <span class="badge ${getStatusBadgeClass(task.status)}">
                        ${capitalizeFirst(task.status)}
                    </span>
                </td>
                <td style="width: 10%">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editTask(${task.task_id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="toggleTaskStatus(${task.task_id}, '${task.status}')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteTask(${task.task_id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            taskTable.appendChild(row);
        });
    } catch (error) {
        console.error('Error loading tasks:', error);
        showAlert('error', 'Failed to load tasks');
    }
}

// Load Timeline
async function loadTimeline() {
    try {
        const response = await fetch(`api/tasks.php?action=timeline&project_id=${projectId}`);
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error || 'Failed to load timeline');
        
        const timeline = document.getElementById('taskTimeline');
        timeline.innerHTML = '';
        
        data.timeline.forEach(task => {
            const item = document.createElement('div');
            item.className = 'timeline-item';
            item.innerHTML = `
                <div class="timeline-marker bg-${getStatusColor(task.status)}"></div>
                <div class="timeline-content">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0">${escapeHtml(task.task_name)}</h6>
                        <span class="badge ${getStatusBadgeClass(task.status)}">
                            ${capitalizeFirst(task.status)}
                        </span>
                    </div>
                    <p class="small text-muted mb-1">
                        ${escapeHtml(task.category_name)} | Assigned to: ${escapeHtml(task.assignee_name)}
                    </p>
                    <p class="small mb-0">Due: ${formatDate(task.due_date)}</p>
                </div>
            `;
            timeline.appendChild(item);
        });
    } catch (error) {
        console.error('Error loading timeline:', error);
        showAlert('error', 'Failed to load timeline');
    }
}

// Update Progress
async function updateProgress() {
    try {
        const response = await fetch(`api/tasks.php?action=progress&project_id=${projectId}`);
        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error || 'Failed to load progress');
        
        const progress = data.progress;
        
        // Update progress bar
        const progressBar = document.getElementById('projectProgress');
        progressBar.style.width = `${progress.progress_percentage}%`;
        progressBar.textContent = `${progress.progress_percentage}%`;
        
        // Update statistics
        document.getElementById('totalTasks').textContent = progress.total_tasks;
        document.getElementById('completedTasks').textContent = progress.completed_tasks;
        document.getElementById('pendingTasks').textContent = progress.pending_tasks;
    } catch (error) {
        console.error('Error updating progress:', error);
        showAlert('error', 'Failed to update progress');
    }
}

// Save Category
async function saveCategory() {
    const categoryName = document.getElementById('categoryName').value.trim();
    const categoryDescription = document.getElementById('categoryDescription').value.trim();
    
    if (!categoryName) {
        showAlert('error', 'Category name is required');
        return;
    }
    
    try {
        const response = await fetch(`api/tasks.php?action=category&project_id=${projectId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                category_name: categoryName,
                description: categoryDescription
            })
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Failed to save category');
        
        // Close modal and reset form
        const modal = bootstrap.Modal.getInstance(document.getElementById('addCategoryModal'));
        modal.hide();
        document.getElementById('addCategoryForm').reset();
        
        // Reload categories
        loadCategories();
        showAlert('success', 'Category added successfully');
    } catch (error) {
        console.error('Error saving category:', error);
        showAlert('error', 'Failed to save category');
    }
}

// Load Project Personnel
async function loadProjectPersonnel() {
    try {
        const response = await fetch(`search_personnel.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `search_term=&project_id=${projectId}`
        });

        const data = await response.json();
        
        if (!response.ok) throw new Error(data.error || 'Failed to load personnel');

        availablePersonnel = data.personnel || [];
        updatePersonnelList();
    } catch (error) {
        console.error('Error loading personnel:', error);
        showAlert('error', 'Failed to load personnel');
    }
}

// Update Personnel List
function updatePersonnelList() {
    const personnelList = document.getElementById('assigneeListContainer');
    personnelList.innerHTML = '';

    const unassignedPersonnel = availablePersonnel.filter(person => 
        !selectedAssignees.has(String(person.user_id))
    );

    if (unassignedPersonnel.length > 0) {
        unassignedPersonnel.forEach(person => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${escapeHtml(person.name)}</div>
                        <small class="text-muted">${escapeHtml(person.email)}</small>
                    </div>
                    <span class="badge bg-secondary">${capitalizeFirst(person.role)}</span>
                </div>
            `;
            
            item.addEventListener('click', () => selectAssignee(person));
            personnelList.appendChild(item);
        });
    } else {
        personnelList.innerHTML = `
            <div class="list-group-item text-muted">
                No more personnel available
            </div>
        `;
    }

    // Update selected assignees display
    updateSelectedAssigneesDisplay();
}

// Update Selected Assignees Display
function updateSelectedAssigneesDisplay() {
    const selectedAssigneesDiv = document.getElementById('selectedAssignees');
    const assigneesInput = document.getElementById('taskAssignees');
    
    selectedAssigneesDiv.innerHTML = '';
    
    const selectedPersonnel = availablePersonnel.filter(person => 
        selectedAssignees.has(String(person.user_id))
    );

    selectedPersonnel.forEach(person => {
        const assigneeElement = document.createElement('div');
        assigneeElement.className = 'mb-2 d-flex align-items-center gap-2 p-2 border rounded';
        assigneeElement.innerHTML = `
            <div>
                <div class="fw-bold">${escapeHtml(person.name)}</div>
                <small class="text-muted">${escapeHtml(person.email)}</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-assignee" 
                    data-user-id="${person.user_id}">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add click event directly to the button
        const removeButton = assigneeElement.querySelector('.remove-assignee');
        removeButton.addEventListener('click', () => removeAssignee(String(person.user_id)));

        selectedAssigneesDiv.appendChild(assigneeElement);
    });

    // Update hidden input with JSON string of selected assignee IDs
    assigneesInput.value = JSON.stringify([...selectedAssignees]);
}

// Select Assignee
function selectAssignee(person) {
    selectedAssignees.add(String(person.user_id));
    updatePersonnelList();
}

// Remove Assignee
function removeAssignee(userId) {
    userId = String(userId);
    selectedAssignees.delete(userId);
    updatePersonnelList();
}

// Clear all assignees
function clearAssignees() {
    selectedAssignees.clear();
    updatePersonnelList();
}

// Save Task
async function saveTask() {
    const taskName = document.getElementById('taskName').value.trim();
    const categoryId = document.getElementById('taskCategory').value;
    const assignees = Array.from(selectedAssignees); // Convert Set to Array
    const dueDate = document.getElementById('taskDueDate').value;
    const description = document.getElementById('taskDescription').value.trim();
    
    if (!taskName || !categoryId || assignees.length === 0 || !dueDate) {
        showAlert('error', 'Please fill in all required fields and select at least one assignee');
        return;
    }
    
    try {
        const response = await fetch(`api/tasks.php?action=task&project_id=${projectId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_name: taskName,
                category_id: categoryId,
                assignees: assignees,
                due_date: dueDate,
                description: description
            })
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Failed to save task');
        
        // Close modal and reset form
        const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
        modal.hide();
        document.getElementById('addTaskForm').reset();
        selectedAssignees.clear(); // Clear selected assignees
        
        // Reset the assignees display
        const selectedAssigneesDiv = document.getElementById('selectedAssignees');
        selectedAssigneesDiv.innerHTML = '';
        document.getElementById('taskAssignees').value = '';
        
        // Hide the personnel list if it's visible
        document.getElementById('assigneeListContainer').style.display = 'none';
        
        // Reload data
        loadTasks();
        loadTimeline();
        updateProgress();
        showAlert('success', 'Task added successfully');
    } catch (error) {
        console.error('Error saving task:', error);
        showAlert('error', 'Failed to save task: ' + error.message);
    }
}

// Toggle Task Status
async function toggleTaskStatus(taskId, currentStatus) {
    const newStatus = currentStatus === 'completed' ? 'pending' : 'completed';
    
    try {
        const response = await fetch(`api/tasks.php?action=task&project_id=${projectId}&id=${taskId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: newStatus
            })
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Failed to update task status');
        
        // Reload data
        loadTasks();
        loadTimeline();
        updateProgress();
        showAlert('success', 'Task status updated');
    } catch (error) {
        console.error('Error updating task status:', error);
        showAlert('error', 'Failed to update task status');
    }
}

// Delete Category
async function deleteCategory(categoryId) {
    if (!confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`api/tasks.php?action=category&project_id=${projectId}&id=${categoryId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Failed to delete category');
        
        loadCategories();
        showAlert('success', 'Category deleted successfully');
    } catch (error) {
        console.error('Error deleting category:', error);
        showAlert('error', 'Failed to delete category');
    }
}

// Delete Task
async function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`api/tasks.php?action=task&project_id=${projectId}&id=${taskId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Failed to delete task');
        
        // Reload data
        loadTasks();
        loadTimeline();
        updateProgress();
        showAlert('success', 'Task deleted successfully');
    } catch (error) {
        console.error('Error deleting task:', error);
        showAlert('error', 'Failed to delete task');
    }
}

// Utility Functions
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function getStatusBadgeClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-success';
        case 'pending':
            return 'bg-warning';
        default:
            return 'bg-secondary';
    }
}

function getStatusColor(status) {
    switch (status) {
        case 'completed':
            return 'success';
        case 'pending':
            return 'warning';
        default:
            return 'secondary';
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
} 