// 全局状态
let currentUser = null;
let currentScanType = 'inbound'; // inbound 或 outbound
let scanItems = [];
let currentRecordType = 'inbound';

// 工具函数
function showToast(message, duration = 2000) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

function showLoading() {
    document.getElementById('loading').classList.add('show');
}

function hideLoading() {
    document.getElementById('loading').classList.remove('show');
}

function showPage(pageId) {
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    document.getElementById(pageId).classList.add('active');
}

// 登录
async function login() {
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;

    if (!username || !password) {
        showToast('请输入用户名和密码');
        return;
    }

    showLoading();
    try {
        const result = await API.auth.login(username, password);
        hideLoading();

        if (result.success) {
            Storage.set('token', result.data.token);
            Storage.set('user', result.data.user);
            currentUser = result.data.user;
            showToast('登录成功');
            showMainPage();
        } else {
            showToast(result.message);
        }
    } catch (error) {
        hideLoading();
        showToast('登录失败，请稍后重试');
    }
}

// 注册
async function register() {
    const username = document.getElementById('regUsername').value.trim();
    const password = document.getElementById('regPassword').value;
    const passwordConfirm = document.getElementById('regPasswordConfirm').value;
    const realname = document.getElementById('regRealname').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const email = document.getElementById('regEmail').value.trim();

    if (!username || !password) {
        showToast('用户名和密码不能为空');
        return;
    }

    if (password !== passwordConfirm) {
        showToast('两次密码输入不一致');
        return;
    }

    showLoading();
    try {
        const result = await API.auth.register({
            username, password, realname, phone, email
        });
        hideLoading();

        if (result.success) {
            showToast('注册成功，请登录');
            showLoginPage();
        } else {
            showToast(result.message);
        }
    } catch (error) {
        hideLoading();
        showToast('注册失败，请稍后重试');
    }
}

// 退出登录
function logout() {
    if (confirm('确定要退出登录吗？')) {
        Storage.clear();
        currentUser = null;
        showLoginPage();
        showToast('已退出登录');
    }
}

// 显示登录页
function showLoginPage() {
    showPage('loginPage');
    document.getElementById('loginUsername').value = '';
    document.getElementById('loginPassword').value = '';
}

// 显示注册页
function showRegisterPage() {
    showPage('registerPage');
}

// 显示主页面
function showMainPage() {
    showPage('mainPage');
    loadUserInfo();
    loadProducts();
}

// 加载用户信息
async function loadUserInfo() {
    const user = Storage.get('user');
    if (user) {
        document.getElementById('usernameDisplay').textContent = user.username;
        document.getElementById('profileName').textContent = user.realname || user.username;
        document.getElementById('profileRole').textContent = user.role === 'admin' ? '管理员' : '普通用户';

        // 显示管理员菜单
        if (user.role === 'admin') {
            document.getElementById('dashboardMenuItem').style.display = 'flex';
            document.getElementById('adminMenuItem').style.display = 'flex';
        }

        // 加载统计信息
        loadUserStats();
    }
}

// 加载用户统计
async function loadUserStats() {
    try {
        const result = await API.auth.stats();
        if (result.success) {
            const stats = result.data;
            document.getElementById('profileStats').innerHTML = `
                <div class="stat-item">
                    <div class="stat-value">${stats.total_inbound}</div>
                    <div class="stat-label">入库次数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${stats.total_outbound}</div>
                    <div class="stat-label">出库次数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${stats.total_operations}</div>
                    <div class="stat-label">总操作</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('加载统计失败', error);
    }
}

// 切换标签页
function switchTab(tab) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.closest('.nav-item').classList.add('active');

    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    switch(tab) {
        case 'home':
            document.getElementById('homeTab').classList.add('active');
            loadProducts();
            break;
        case 'categories':
            document.getElementById('categoriesTab').classList.add('active');
            loadCategories();
            break;
        case 'scan':
            document.getElementById('scanTab').classList.add('active');
            scanItems = [];
            renderScanList();
            break;
        case 'records':
            document.getElementById('recordsTab').classList.add('active');
            loadRecords();
            break;
        case 'profile':
            document.getElementById('profileTab').classList.add('active');
            loadUserStats();
            break;
    }
}

// 搜索商品
async function searchProducts() {
    const keyword = document.getElementById('searchKeyword').value.trim();
    loadProducts({ keyword });
}

// 加载商品列表
async function loadProducts(params = {}) {
    showLoading();
    try {
        const result = await API.products.list(params);
        hideLoading();

        if (result.success) {
            renderProductList(result.data.list);
        }
    } catch (error) {
        hideLoading();
        showToast('加载商品失败');
    }
}

// 渲染商品列表
function renderProductList(products) {
    const container = document.getElementById('productList');
    if (products.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:#999;">暂无商品</div>';
        return;
    }

    container.innerHTML = products.map(product => `
        <div class="product-item" onclick="showProductDetail(${product.id})">
            <div class="product-image">🛞</div>
            <div class="product-info">
                <div class="product-name">${product.name}</div>
                <div class="product-model">${product.model}</div>
                <div class="product-price">¥${parseFloat(product.price).toFixed(2)}</div>
                <div class="product-stock">库存: ${product.total_stock || 0}</div>
            </div>
        </div>
    `).join('');
}

// 显示商品详情
async function showProductDetail(productId) {
    showLoading();
    try {
        const result = await API.products.detail(productId);
        hideLoading();

        if (result.success) {
            const product = result.data;
            document.getElementById('productDetail').innerHTML = `
                <div class="product-detail-image">🛞</div>
                <div class="product-detail-info">
                    <div class="product-detail-title">${product.name}</div>
                    <div class="product-detail-price">¥${parseFloat(product.price).toFixed(2)}</div>
                    <div class="product-detail-row">
                        <span class="detail-label">型号</span>
                        <span class="detail-value">${product.model}</span>
                    </div>
                    <div class="product-detail-row">
                        <span class="detail-label">分类</span>
                        <span class="detail-value">${product.category1_name} / ${product.category2_name} / ${product.category3_name}</span>
                    </div>
                    <div class="product-detail-row">
                        <span class="detail-label">总库存</span>
                        <span class="detail-value">${product.total_stock || 0}</span>
                    </div>
                </div>
                <div class="product-barcodes">
                    <div class="barcodes-title">关联条形码</div>
                    ${product.barcodes.map(barcode => `
                        <div class="barcode-item">
                            <div class="barcode-number">${barcode.barcode}</div>
                            <div class="barcode-stock">库存: ${barcode.stock} | 位置: ${barcode.location || '未设置'}</div>
                        </div>
                    `).join('')}
                </div>
            `;
            showPage('productDetailPage');
        }
    } catch (error) {
        hideLoading();
        showToast('加载详情失败');
    }
}

// 关闭商品详情
function closeProductDetail() {
    showPage('mainPage');
}

// 加载分类
async function loadCategories() {
    try {
        const result = await API.categories.getAll();
        if (result.success) {
            renderCategories(result.data);
        }
    } catch (error) {
        showToast('加载分类失败');
    }
}

// 渲染分类
function renderCategories(categories) {
    const level1Container = document.getElementById('categoryLevel1');
    level1Container.innerHTML = categories.map(cat => `
        <div class="category-item" onclick="selectCategory1(${cat.id}, '${cat.name}', ${JSON.stringify(cat.children).replace(/"/g, '&quot;')})">
            ${cat.name}
        </div>
    `).join('');
}

// 选择一级分类
function selectCategory1(id, name, children) {
    document.querySelectorAll('#categoryLevel1 .category-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.classList.add('active');

    const level2Container = document.getElementById('categoryLevel2');
    level2Container.innerHTML = children.map(cat => `
        <div class="category-item" onclick="selectCategory2(${id}, ${cat.id}, '${cat.name}', ${JSON.stringify(cat.children).replace(/"/g, '&quot;')})">
            ${cat.name}
        </div>
    `).join('');

    document.getElementById('categoryLevel3').innerHTML = '';
    document.getElementById('categoryProducts').innerHTML = '';
}

// 选择二级分类
function selectCategory2(cat1Id, cat2Id, name, children) {
    document.querySelectorAll('#categoryLevel2 .category-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.classList.add('active');

    const level3Container = document.getElementById('categoryLevel3');
    level3Container.innerHTML = children.map(cat => `
        <div class="category-item" onclick="selectCategory3(${cat1Id}, ${cat2Id}, ${cat.id})">
            ${cat.name}
        </div>
    `).join('');

    document.getElementById('categoryProducts').innerHTML = '';
}

// 选择三级分类
async function selectCategory3(cat1Id, cat2Id, cat3Id) {
    document.querySelectorAll('#categoryLevel3 .category-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.classList.add('active');

    await loadProducts({
        category1_id: cat1Id,
        category2_id: cat2Id,
        category3_id: cat3Id
    });

    const products = await API.products.list({
        category1_id: cat1Id,
        category2_id: cat2Id,
        category3_id: cat3Id
    });

    if (products.success) {
        const container = document.getElementById('categoryProducts');
        if (products.data.list.length === 0) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:#999;">该分类下暂无商品</div>';
        } else {
            container.innerHTML = products.data.list.map(product => `
                <div class="product-item" onclick="showProductDetail(${product.id})">
                    <div class="product-image">🛞</div>
                    <div class="product-info">
                        <div class="product-name">${product.name}</div>
                        <div class="product-model">${product.model}</div>
                        <div class="product-price">¥${parseFloat(product.price).toFixed(2)}</div>
                        <div class="product-stock">库存: ${product.total_stock || 0}</div>
                    </div>
                </div>
            `).join('');
        }
    }
}

// 切换扫码类型
function switchScanType(type) {
    currentScanType = type;
    document.querySelectorAll('.scan-type-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');

    // 出库时显示车牌号输入
    document.getElementById('outboundExtra').style.display = type === 'outbound' ? 'block' : 'none';

    scanItems = [];
    renderScanList();
}

// 开始扫码
async function startScan() {
    try {
        const barcode = await Scanner.scan();
        await addScanItem(barcode);
    } catch (error) {
        if (error !== '取消扫码') {
            showToast('扫码失败: ' + error);
        }
    }
}

// 添加扫描项
async function addScanItem(barcode) {
    showLoading();
    try {
        const result = await API.barcodes.getByBarcode(barcode);
        hideLoading();

        if (result.success) {
            const existing = scanItems.find(item => item.barcode === barcode);
            if (existing) {
                existing.quantity++;
            } else {
                scanItems.push({
                    barcode: barcode,
                    barcode_id: result.data.id,
                    product_id: result.data.product_id,
                    product_name: result.data.product_name,
                    product_model: result.data.product_model,
                    stock: result.data.stock,
                    quantity: 1
                });
            }
            renderScanList();
            showToast('添加成功');
        } else {
            showToast(result.message);
        }
    } catch (error) {
        hideLoading();
        showToast('查询条形码失败');
    }
}

// 渲染扫描列表
function renderScanList() {
    const container = document.getElementById('scanList');
    if (scanItems.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:#999;">暂无扫描记录</div>';
        return;
    }

    container.innerHTML = scanItems.map((item, index) => `
        <div class="scan-item">
            <div class="scan-item-info">
                <div class="scan-item-barcode">${item.barcode}</div>
                <div class="scan-item-name">${item.product_name} - ${item.product_model}</div>
                <div class="scan-item-name">当前库存: ${item.stock}</div>
            </div>
            <div class="scan-item-qty">
                <input type="number" value="${item.quantity}" min="1"
                    onchange="updateScanQuantity(${index}, this.value)">
                <span class="scan-item-delete" onclick="removeScanItem(${index})">🗑️</span>
            </div>
        </div>
    `).join('');
}

// 更新扫描数量
function updateScanQuantity(index, quantity) {
    scanItems[index].quantity = parseInt(quantity) || 1;
}

// 移除扫描项
function removeScanItem(index) {
    scanItems.splice(index, 1);
    renderScanList();
}

// 拍照识别车牌
async function captureLicensePlate() {
    try {
        const result = await LicensePlateRecognizer.capture();
        if (result.plate) {
            document.getElementById('licensePlate').value = result.plate;
        }
    } catch (error) {
        showToast('识别失败');
    }
}

// 提交扫码
async function submitScan() {
    if (scanItems.length === 0) {
        showToast('请先扫描条形码');
        return;
    }

    const items = scanItems.map(item => ({
        barcode: item.barcode,
        quantity: item.quantity
    }));

    showLoading();
    try {
        let result;
        if (currentScanType === 'inbound') {
            result = await API.inventory.batchInbound(items);
        } else {
            const licensePlate = document.getElementById('licensePlate').value.trim();
            result = await API.inventory.batchOutbound(items, licensePlate);
        }

        hideLoading();

        if (result.success) {
            showToast(`${currentScanType === 'inbound' ? '入库' : '出库'}成功`);
            scanItems = [];
            renderScanList();
            if (currentScanType === 'outbound') {
                document.getElementById('licensePlate').value = '';
            }
        } else {
            showToast(result.message);
        }
    } catch (error) {
        hideLoading();
        showToast('操作失败');
    }
}

// 切换记录类型
function switchRecordType(type) {
    currentRecordType = type;
    document.querySelectorAll('.record-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');

    // 出库记录显示车牌号搜索
    document.getElementById('recordLicensePlate').style.display = type === 'outbound' ? 'block' : 'none';

    loadRecords();
}

// 搜索记录
function searchRecords() {
    loadRecords();
}

// 加载记录
async function loadRecords() {
    const params = {
        start_date: document.getElementById('recordStartDate').value,
        end_date: document.getElementById('recordEndDate').value,
        barcode: document.getElementById('recordBarcode').value.trim()
    };

    if (currentRecordType === 'outbound') {
        params.license_plate = document.getElementById('recordLicensePlate').value.trim();
    }

    showLoading();
    try {
        const result = currentRecordType === 'inbound'
            ? await API.records.inbound(params)
            : await API.records.outbound(params);
        hideLoading();

        if (result.success) {
            renderRecordsList(result.data.list);
        }
    } catch (error) {
        hideLoading();
        showToast('加载记录失败');
    }
}

// 渲染记录列表
function renderRecordsList(records) {
    const container = document.getElementById('recordsList');
    if (records.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:#999;">暂无记录</div>';
        return;
    }

    container.innerHTML = records.map(record => `
        <div class="record-item">
            <div class="record-header">
                <span class="record-type">${currentRecordType === 'inbound' ? '入库' : '出库'}</span>
                <span class="record-time">${record[currentRecordType + '_time']}</span>
            </div>
            <div class="record-detail">
                条形码: ${record.barcode}<br>
                商品: ${record.product_name} - ${record.product_model}<br>
                数量: ${record.quantity}<br>
                ${currentRecordType === 'outbound' && record.license_plate ? `车牌号: ${record.license_plate}<br>` : ''}
                操作人: ${record.operator_name}
            </div>
        </div>
    `).join('');
}

// 修改密码
function showChangePassword() {
    const oldPassword = prompt('请输入旧密码:');
    if (!oldPassword) return;

    const newPassword = prompt('请输入新密码(至少6位):');
    if (!newPassword || newPassword.length < 6) {
        showToast('新密码长度不能少于6位');
        return;
    }

    const confirmPassword = prompt('请再次输入新密码:');
    if (newPassword !== confirmPassword) {
        showToast('两次密码输入不一致');
        return;
    }

    changePassword(oldPassword, newPassword);
}

async function changePassword(oldPassword, newPassword) {
    showLoading();
    try {
        const result = await API.auth.changePassword(oldPassword, newPassword);
        hideLoading();

        if (result.success) {
            showToast('密码修改成功，请重新登录');
            setTimeout(() => {
                logout();
            }, 1500);
        } else {
            showToast(result.message);
        }
    } catch (error) {
        hideLoading();
        showToast('密码修改失败');
    }
}

// 显示数据大屏
function showDashboard() {
    // TODO: 实现数据大屏
    showToast('功能开发中');
}

// 显示管理后台
function showAdminPanel() {
    showPage('adminPage');
    switchAdminTab('products');
}

// 关闭管理后台
function closeAdminPanel() {
    showPage('mainPage');
}

// 切换管理标签
function switchAdminTab(tab) {
    document.querySelectorAll('.admin-tab').forEach(t => {
        t.classList.remove('active');
    });
    event.target.classList.add('active');

    // TODO: 加载对应的管理内容
    document.getElementById('adminContent').innerHTML = `
        <div style="text-align:center;padding:40px;color:#999;">
            ${tab} 管理功能开发中...
        </div>
    `;
}

// 初始化
window.onload = function() {
    const token = Storage.get('token');
    if (token) {
        currentUser = Storage.get('user');
        showMainPage();
    } else {
        showLoginPage();
    }
};
